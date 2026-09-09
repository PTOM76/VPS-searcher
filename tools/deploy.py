#!/usr/bin/env python3
"""Git 追跡ファイルのみを FTPS / SFTP でサーバへ差分アップロードする。

安全設計:
  - 転送対象は `git ls-files` の結果から .deploy-ignore の除外を引いたもの。
    追跡外の vendor/ は送らないため、依存関係はサーバ側で composer install する。
  - 例外として .deploy-include に書いたパスは、git 追跡外でも毎回そのまま送る。
    ビルド成果物 (public/build) のように「リポジトリに置きたくないが
    サーバには要る」ものを、履歴を汚さずに届けるための口。
  - サーバ側ファイルの削除は既定で行わない。--delete を明示した時だけ。
  - 前回デプロイ成功時のコミットを .deploy-state に記録し、次回はその差分のみ送る。

使い方:
    python tools/deploy.py            # 前回からの差分をアップロード
    python tools/deploy.py --dry-run  # 転送せず対象ファイル一覧だけ表示
    python tools/deploy.py --full     # 差分ではなく追跡ファイル全件
    python tools/deploy.py --delete   # サーバ側の削除も反映 (要確認)

サーバに既に一式ある状態から使い始める場合は、まず --set-state で
現在のコミットをデプロイ済みとして記録する。以降は git の差分だけが対象になる。
"""

from __future__ import annotations

import argparse
import fnmatch
import ftplib
import io
import os
import posixpath
import subprocess
import sys
from pathlib import Path

# パイプ経由など出力先が日本語を扱えない場合に落ちないようにする。
for stream in (sys.stdout, sys.stderr):
    try:
        stream.reconfigure(errors="replace")
    except (AttributeError, ValueError):
        pass

REPO_ROOT = Path(__file__).resolve().parent.parent
STATE_FILE = REPO_ROOT / ".deploy-state"
IGNORE_FILE = REPO_ROOT / ".deploy-ignore"
INCLUDE_FILE = REPO_ROOT / ".deploy-include"

# .deploy-ignore が無いときの既定 (詳しい理由はそのファイルのコメントを参照)。
DEFAULT_EXCLUDES = ("tools/", "storage/", ".vscode/", ".github/", ".user.ini")
# どこに置いても読めるようにする (いずれも .gitignore 済み)
ENV_CANDIDATES = (
    REPO_ROOT / "tools" / ".deploy-env",
    REPO_ROOT / "tools" / "deploy-env",
    REPO_ROOT / ".deploy-env",
)


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    raise SystemExit(1)


def git(*args: str) -> str:
    # Windows 既定の cp932 だと UTF-8 のパスや差分でデコードに失敗するため明示する。
    # core.quotepath=false で日本語ファイル名がエスケープされるのも防ぐ。
    result = subprocess.run(
        ["git", "-c", "core.quotepath=false", *args],
        cwd=REPO_ROOT,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    if result.returncode != 0:
        fail(f"git {' '.join(args)} が失敗しました\n{result.stderr.strip()}")
    return result.stdout


def git_blob(rev: str, path: str) -> bytes:
    """指定リビジョンのファイル内容を「そのまま」取り出す。

    core.autocrlf=true の環境では作業ツリーが CRLF になるため、作業ツリーの
    ファイルを送るとサーバ側 (LF) との差が全ファイルに出てしまう。
    リポジトリに格納された内容 (LF) を送ることで改行の揺れを持ち込まない。
    """
    result = subprocess.run(
        ["git", "show", f"{rev}:{path}"], cwd=REPO_ROOT, capture_output=True
    )
    if result.returncode != 0:
        fail(f"git show {rev}:{path} が失敗しました\n"
             f"{result.stderr.decode('utf-8', 'replace').strip()}")
    return result.stdout


# --------------------------------------------------------------------------
# 設定
# --------------------------------------------------------------------------

CONFIG_KEYS = (
    "FTP_PROTOCOL", "FTP_HOST", "FTP_PORT", "FTP_USER",
    "FTP_PASS", "FTP_KEY", "FTP_DIR", "FTP_TLS",
)


def load_config() -> dict[str, str]:
    """設定ファイルと環境変数から設定を読む。環境変数が優先。"""
    config: dict[str, str] = {}

    env_file = next((p for p in ENV_CANDIDATES if p.is_file()), None)
    if env_file is None:
        names = " または ".join(str(p) for p in ENV_CANDIDATES)
        fail(f"設定ファイルがありません: {names}")

    for raw in env_file.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        config[key.strip()] = value.strip().strip('"').strip("'")

    for key in CONFIG_KEYS:
        if os.environ.get(key):
            config[key] = os.environ[key]

    # FTP_PROTOCOL 未指定時は、旧来の FTP_TLS から解釈する。
    protocol = (config.get("FTP_PROTOCOL") or "").lower()
    if not protocol:
        tls_off = (config.get("FTP_TLS") or "1").lower() in ("0", "false", "no")
        protocol = "ftp" if tls_off else "ftps"
    if protocol not in ("ftps", "ftp", "sftp"):
        fail(f"FTP_PROTOCOL は ftps / ftp / sftp のいずれかです: {protocol}")
    config["FTP_PROTOCOL"] = protocol

    for key in ("FTP_HOST", "FTP_USER", "FTP_DIR"):
        if not config.get(key):
            fail(f"{key} が未設定です ({env_file})")

    # Git Bash (MSYS) は環境変数の "/virtual/..." を "C:/Program Files/Git/virtual/..."
    # に書き換えてしまう。そのまま転送すると本番ではない場所にファイルが散らばるため、
    # Windows のパスに見えるものは受け付けない。
    remote_dir = config["FTP_DIR"]
    if not remote_dir.startswith("/") or ":" in remote_dir or "\\" in remote_dir:
        fail(f"FTP_DIR が不正です: {remote_dir}\n"
             "サーバ上の絶対パスを指定してください。Git Bash から環境変数で渡すと\n"
             "パスが変換されることがあります (MSYS_NO_PATHCONV=1 を付けるか、\n"
             "設定ファイルに書いてください)。")

    if not config.get("FTP_PASS") and not config.get("FTP_KEY"):
        fail("FTP_PASS または FTP_KEY が必要です。")

    return config


# --------------------------------------------------------------------------
# 転送先
# --------------------------------------------------------------------------

class Transport:
    """FTPS と SFTP の差を吸収する。"""

    def __init__(self) -> None:
        self._known_dirs: set[str] = set()

    def ensure_dir(self, remote_dir: str) -> None:
        """親をたどってリモートディレクトリを作る (存在すれば何もしない)。"""
        if not remote_dir or remote_dir == "/" or remote_dir in self._known_dirs:
            return
        parent = posixpath.dirname(remote_dir)
        if parent and parent != remote_dir:
            self.ensure_dir(parent)
        self._mkdir(remote_dir)
        self._known_dirs.add(remote_dir)

    def _mkdir(self, remote_dir: str) -> None:
        raise NotImplementedError

    def upload(self, data: bytes, remote: str) -> None:
        raise NotImplementedError

    def remove(self, remote: str) -> None:
        raise NotImplementedError

    def close(self) -> None:
        raise NotImplementedError


class FtpTransport(Transport):
    def __init__(self, config: dict[str, str]) -> None:
        super().__init__()
        use_tls = config["FTP_PROTOCOL"] == "ftps"
        self.ftp = ftplib.FTP_TLS() if use_tls else ftplib.FTP()
        self.ftp.connect(config["FTP_HOST"], int(config.get("FTP_PORT") or 21), timeout=30)
        self.ftp.login(config["FTP_USER"], config["FTP_PASS"])
        if use_tls:
            self.ftp.prot_p()
        self.ftp.set_pasv(True)

    def _mkdir(self, remote_dir: str) -> None:
        try:
            self.ftp.mkd(remote_dir)
        except ftplib.error_perm as exc:
            # 550 は「既に存在」が大半。それ以外は握りつぶさない。
            if not str(exc).startswith("550"):
                raise

    def upload(self, data: bytes, remote: str) -> None:
        self.ftp.storbinary(f"STOR {remote}", io.BytesIO(data))

    def remove(self, remote: str) -> None:
        self.ftp.delete(remote)

    def close(self) -> None:
        try:
            self.ftp.quit()
        except Exception:
            self.ftp.close()


class SftpTransport(Transport):
    def __init__(self, config: dict[str, str]) -> None:
        super().__init__()
        try:
            import paramiko
        except ImportError:
            fail("SFTP には paramiko が必要です: python -m pip install paramiko")

        self.client = paramiko.SSHClient()
        self.client.load_system_host_keys()
        self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        key = config.get("FTP_KEY")
        self.client.connect(
            config["FTP_HOST"],
            port=int(config.get("FTP_PORT") or 22),
            username=config["FTP_USER"],
            password=config.get("FTP_PASS") or None,
            key_filename=key or None,
            timeout=30,
            look_for_keys=bool(key),
            allow_agent=False,
        )
        self.sftp = self.client.open_sftp()

    def _mkdir(self, remote_dir: str) -> None:
        try:
            self.sftp.stat(remote_dir)
        except IOError:
            self.sftp.mkdir(remote_dir)

    def upload(self, data: bytes, remote: str) -> None:
        with self.sftp.open(remote, "wb") as handle:
            handle.write(data)

    def remove(self, remote: str) -> None:
        self.sftp.remove(remote)

    def close(self) -> None:
        try:
            self.sftp.close()
        finally:
            self.client.close()


def open_transport(config: dict[str, str]) -> Transport:
    if config["FTP_PROTOCOL"] == "sftp":
        return SftpTransport(config)
    return FtpTransport(config)


# --------------------------------------------------------------------------
# 差分の算出
# --------------------------------------------------------------------------

def load_excludes() -> tuple[str, ...]:
    """.deploy-ignore を読む。無ければ既定の除外を使う。

    書式は 1 行 1 パターン。# 以降は無視。
      site/           末尾が / ならそのディレクトリ以下すべて
      tools/deploy.py パス完全一致
      *.md            glob (パス全体に対して照合)
    """
    if not IGNORE_FILE.is_file():
        return DEFAULT_EXCLUDES

    patterns = []
    for raw in IGNORE_FILE.read_text(encoding="utf-8").splitlines():
        line = raw.split("#", 1)[0].strip()
        if line:
            patterns.append(line)
    return tuple(patterns)


def is_excluded(path: str, excludes: tuple[str, ...]) -> bool:
    for pattern in excludes:
        if pattern.endswith("/"):
            if path.startswith(pattern):
                return True
        elif path == pattern or fnmatch.fnmatch(path, pattern):
            return True
    return False


def read_state() -> str | None:
    if not STATE_FILE.exists():
        return None
    return STATE_FILE.read_text(encoding="utf-8").strip() or None


def commit_exists(sha: str) -> bool:
    result = subprocess.run(
        ["git", "cat-file", "-e", f"{sha}^{{commit}}"],
        cwd=REPO_ROOT,
        capture_output=True,
    )
    return result.returncode == 0


def load_includes() -> tuple[str, ...]:
    """.deploy-include を読む。

    git 追跡外でも毎回送るパスを 1 行 1 件で書く。ディレクトリなら末尾に / を付ける。
    差分計算は git の履歴を見るので、追跡外のファイルは差分に現れない。
    そのため、ここに挙げたものは毎回まるごと送る。
    """
    if not INCLUDE_FILE.is_file():
        return ()

    patterns = []
    for raw in INCLUDE_FILE.read_text(encoding="utf-8").splitlines():
        line = raw.split("#", 1)[0].strip()
        if line:
            patterns.append(line)
    return tuple(patterns)


def matches_any(path: str, patterns: tuple[str, ...]) -> bool:
    """パスが .deploy-include のいずれかに含まれるか。"""
    for pattern in patterns:
        if pattern.endswith("/"):
            if path.startswith(pattern):
                return True
        elif path == pattern:
            return True
    return False


def collect_forced(includes: tuple[str, ...]) -> list[str]:
    """.deploy-include に挙げたパスを、実在するファイルへ展開する。"""
    found: set[str] = set()
    for pattern in includes:
        target = REPO_ROOT / pattern.rstrip("/")
        if target.is_dir():
            for path in target.rglob("*"):
                if path.is_file():
                    found.add(path.relative_to(REPO_ROOT).as_posix())
        elif target.is_file():
            found.add(target.relative_to(REPO_ROOT).as_posix())

    return sorted(found)


def collect_changes(full: bool, excludes: tuple[str, ...]) -> tuple[list[tuple[str, str]], list[str], str]:
    """(アップロード対象 [(状態, パス)], 削除候補, 基準の説明) を返す。"""
    head = git("rev-parse", "HEAD").strip()
    tracked = {p for p in git("ls-files").splitlines()
               if p and not is_excluded(p, excludes)}

    previous = None if full else read_state()
    if previous and not commit_exists(previous):
        print(f"警告: 記録されたコミット {previous[:8]} が見つかりません。全件転送に切り替えます。")
        previous = None

    if previous is None:
        return [("ALL", p) for p in sorted(tracked)], [], "全追跡ファイル (初回、または --full)"

    if previous == head:
        return [], [], f"{previous[:8]} から変更なし"

    upload: dict[str, str] = {}
    delete: set[str] = set()
    for line in git("diff", "--name-status", "--no-renames", previous, head).splitlines():
        if not line.strip():
            continue
        status, _, path = line.partition("\t")
        path = path.strip()
        if not path:
            continue
        if status.startswith("D"):
            delete.add(path)
        else:
            # A=追加 / M=変更 の区別を一覧に出すため保持する
            upload[path] = status.strip()[:1]

    # 追跡対象外・除外対象は送らない。削除候補も追跡中なら取り消す。
    upload = {p: s for p, s in upload.items() if p in tracked}
    delete = {p for p in delete if not is_excluded(p, excludes)} - tracked

    return (
        sorted((s, p) for p, s in upload.items()),
        sorted(delete),
        f"{previous[:8]} → {head[:8]} の差分",
    )


LABELS = {"A": "追加", "M": "変更", "T": "種別変更", "C": "コピー", "ALL": "全件", "FORCED": "追跡外"}


def print_plan(upload: list[tuple[str, str]], delete: list[str], will_delete: bool) -> None:
    if upload:
        print("--- アップロード対象 ---")
        for status, path in upload:
            print(f"  [{LABELS.get(status, status)}] {path}")
    if delete:
        header = ("--- サーバから削除する対象 ---" if will_delete
                  else "--- Git から消えたファイル (--delete 未指定のためスキップ) ---")
        print(f"\n{header}")
        for path in delete:
            print(f"  [削除] {path}")


# --------------------------------------------------------------------------

def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--dry-run", action="store_true", help="転送せず対象ファイルの一覧だけ表示する"
    )
    parser.add_argument("--full", action="store_true", help="差分ではなく追跡ファイル全件を送る")
    parser.add_argument(
        "--set-state", metavar="COMMIT", nargs="?", const="HEAD",
        help="転送せず、指定コミット (既定 HEAD) をデプロイ済みとして記録する",
    )
    parser.add_argument(
        "--delete", action="store_true",
        help="Git から削除されたファイルをサーバからも削除する",
    )
    args = parser.parse_args()

    if args.set_state:
        sha = git("rev-parse", args.set_state).strip()
        STATE_FILE.write_text(sha + "\n", encoding="utf-8")
        print(f"デプロイ済みとして記録しました: {sha[:8]} ({args.set_state})")
        print("次回以降はこのコミットからの差分だけが対象になります。")
        return 0

    excludes = load_excludes()
    if excludes:
        print("除外パス: " + ", ".join(excludes))

    includes = load_includes()
    if includes:
        print("追跡外でも送る: " + ", ".join(includes))

    if git("status", "--porcelain").strip():
        print("警告: 未コミットの変更があります。デプロイされるのは HEAD の内容です。\n")

    upload, delete, reason = collect_changes(args.full, excludes)

    # 追跡外の強制送信分を足す。git の差分には現れないので毎回まるごと送る
    forced = collect_forced(includes)
    known = {path for _, path in upload}
    upload = sorted(upload + [("FORCED", p) for p in forced if p not in known])

    # 追跡を外した直後は「削除された」ように見えるが、送り続ける対象なので消さない。
    # ここを塞がないと --delete 付きの回に本番のアセットが消える
    delete = [p for p in delete if not matches_any(p, includes)]

    if includes and not forced:
        print("警告: .deploy-include に挙げたパスに送るファイルがありません。"
              "ビルドを実行し忘れていないか確認してください。\n")

    print(f"\n対象: {reason}")
    print(f"アップロード {len(upload)} 件 / 削除候補 {len(delete)} 件\n")
    print_plan(upload, delete, args.delete)

    if not upload and not (delete and args.delete):
        print("\n実行する処理はありません。")
        return 0

    if args.dry_run:
        print("\ndry-run のため転送しませんでした。")
        return 0

    config = load_config()
    remote_root = config["FTP_DIR"].rstrip("/")
    print(f"\n接続中: {config['FTP_PROTOCOL']}://{config['FTP_HOST']} → {remote_root}/")
    transport = open_transport(config)

    uploaded = 0
    deleted = 0
    try:
        for status, path in upload:
            if status == "FORCED":
                # .deploy-include の分は追跡外で HEAD に無い。ディスクから読む
                data = (REPO_ROOT / path).read_bytes()
            else:
                # 作業ツリーではなく HEAD の内容を送る (改行コードを持ち込まないため)
                data = git_blob("HEAD", path)
            remote = f"{remote_root}/{path}"
            transport.ensure_dir(posixpath.dirname(remote))
            transport.upload(data, remote)
            uploaded += 1
            print(f"  UP  {path}")

        if args.delete:
            for path in delete:
                try:
                    transport.remove(f"{remote_root}/{path}")
                    deleted += 1
                    print(f"  DEL {path}")
                except Exception as exc:
                    print(f"  SKIP {path} ({exc})")
    finally:
        transport.close()

    head = git("rev-parse", "HEAD").strip()
    STATE_FILE.write_text(head + "\n", encoding="utf-8")
    print(f"\n完了: アップロード {uploaded} 件 / 削除 {deleted} 件")
    print(f"状態を記録しました: {head[:8]}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
