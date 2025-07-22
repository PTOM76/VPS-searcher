# ボ対検索ツール
ボイパ対決という音MADに特化した検索ツール<br>
2023年から開発してる。

## 技術
### 言語
- PHP - バックエンド
- HTML/JS/CSS - フロントエンド
- JSON - データの保存につかった

### ツール
- Visual Studio Code - ローカルで開発するときに
- GitHub Codespaces - こいつも実質VSCodeではある、クラウドでできる
- EmEditor - すぐに編集したいなってときにつかってる

### API
- YouTube API - つべの再生リストから動画拾うときにつかってる。ニコニコやbilibiliもそれっぽいのねえのかな？
- 自作ニューラルネットワーク - ネーミングセンスが悪いけど動画判別用の「ボ対トル AI」 → ちな、これはオープンにしてない。

### AI
- Claude Snonet 4 - リファクタリングに使ってるAIエージェント、メンテナンスしやすくなった


## プロジェクト構造

```
/
├── index.php             # メインエントリーポイント
├── main.css              # メインスタイルシート
├── main.js               # メインJavaScript
├── darkmode.js           # ダークモード機能
├── {lang}.php            # 各言語用エントリーポイント
├── config.ini.php        # 設定ファイル
├── lang.ini.php          # 言語設定
├── secret.ini.php        # 秘匿情報 (つべのAPIキーのAPI_KEY、管理用のPASSとか)
├── blacklist.json        # ブラックリスト
├── time.txt              # 更新時刻記録
├── lib/                  # ライブラリディレクトリ
│   ├── common.php        # 共通関数
│   ├── config.php        # アプリケーション設定
│   ├── auth.php          # 認証関連
│   └── error_handler.php # エラーハンドリング
├── page/                 # コンテンツページ
│   ├── info.php          # 情報ページ
│   ├── post.php          # 投稿ページ
│   ├── post_admin.php    # 管理者投稿ページ
│   └── report.php        # 報告ページ
├── action/               # ユーザーアクション
│   ├── login.php         # ログイン
│   ├── logout.php        # ログアウト
│   ├── register.php      # 登録
│   ├── account.php       # アカウント管理
│   └── favorites.php     # お気に入り
├── matrix/               # マトリックス表示
│   ├── index.php         # マトリックス表示メイン
│   └── {lang}.php        # 各言語用
├── data/                 # データファイル
│   ├── index.json        # 動画インデックス
│   ├── ai_index.json     # AI分類インデックス
│   ├── playlists.json    # プレイリスト設定
│   ├── analytics/        # アクセス解析
│   └── users.json        # ユーザーデータ
├── cache/                # キャッシュディレクトリ
│   └── thumb/            # サムネイルキャッシュ
├── queue/                # キューディレクトリ
├── report/               # 報告ディレクトリ
└── logs/                 # ログディレクトリ
```

## メンテナンス方法

### 1. 新機能の追加

#### 新しいページを追加する場合
1. `page/` または `action/` ディレクトリに PHP ファイルを作成
2. `index.php` のルーティング部分に条件を追加
3. 必要に応じて言語ファイルを更新

#### 新しい共通機能を追加する場合
1. `lib/common.php` に関数を追加
2. 必要に応じて `lib/config.php` に設定を追加
3. エラーハンドリングを組み込む

### 2. 設定の変更

#### ファイルパスの変更
- `lib/config.php` の `FilePaths` クラスを編集

#### API設定の変更
- `lib/config.php` の `PlaylistConfig` クラスを編集

### 3. バグ修正とデバッグ

#### ログの確認
```bash
tail -f logs/error.log
```

#### 一般的な問題の対処
1. **API エラー**: `logs/error.log` でAPI応答を確認
2. **ファイル権限エラー**: ディレクトリの書き込み権限を確認
3. **データ関連**: `data/` ディレクトリの JSON ファイルを確認
