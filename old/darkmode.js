// ダークモードの実装 :root[theme=dark]を追加

window.addEventListener('load', function () {
    // OSのダークモード設定に合わせる
    var darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    darkModeMediaQuery.addEventListener('change', function (event) {
        var theme = event.matches ? 'dark' : 'light';
        document.documentElement.setAttribute('theme', theme);
        localStorage.setItem('theme', theme);
    });

    if (darkModeMediaQuery.matches) {
        document.documentElement.setAttribute('theme', 'dark');
    } else {
        document.documentElement.setAttribute('theme', 'light');
    }

    // ローカルストレージからダークモード設定を読み込む
    var theme = localStorage.getItem('theme');
    if (theme) {
        document.documentElement.setAttribute('theme', theme);
    }
});

// ダークモードの切り替え
function toggleDarkMode() {
    var theme = document.documentElement.getAttribute('theme');
    var newTheme = theme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('theme', newTheme);
    localStorage.setItem('theme', newTheme);
}
