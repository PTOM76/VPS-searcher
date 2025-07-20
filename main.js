// YouTube動画の埋め込み
function onClickThumb($id) {
    document.getElementById("content_" + $id).innerHTML = '<iframe width="320" height="180" src="https://www.youtube.com/embed/' + $id + '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
}

// ニコニコ動画の埋め込み
function onClickThumbNC($id) {
    var $script = document.createElement('script');
    $script.setAttribute("type", "application/javascript");
    $script.setAttribute("src", "https://embed.nicovideo.jp/watch/" + $id + "/script?w=320&h=180");
    document.getElementById("content_" + $id).innerHTML = '';
    document.getElementById("content_" + $id).appendChild($script);
}

// スマートフォンメニューの開閉
function openSpMenu() {
    var menu = document.getElementById("menu_sp");
    if (menu.getAttribute("data-isopen") === "false" || menu.getAttribute("data-isopen") === null) {
        menu.setAttribute("data-isopen", "true");
    } else {
        menu.setAttribute("data-isopen", "false");
    }
}

// お気に入り機能
function toggleFavorite(videoId, title, thumbnail) {
    if (!window.isLoggedIn) {
        showMessage(window.translations.login_required, 'error');
        return;
    }
    
    const favoriteBtn = document.querySelector(`.favorite-btn[onclick*="${videoId}"]`);
    const isFavorited = favoriteBtn.classList.contains('favorited');
    
    const formData = new FormData();
    formData.append('action', isFavorited ? 'remove_favorite' : 'add_favorite');
    formData.append('video_id', videoId);
    formData.append('title', title);
    formData.append('description', '');
    formData.append('thumbnail', thumbnail);
    
    fetch('ajax/action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isFavorited) {
                favoriteBtn.classList.remove('favorited');
                favoriteBtn.textContent = '☆';
                favoriteBtn.title = window.translations.add_to_favorites;
            } else {
                favoriteBtn.classList.add('favorited');
                favoriteBtn.textContent = '★';
                favoriteBtn.title = window.translations.remove_from_favorites;
            }
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        showMessage(window.translations.error_occurred, 'error');
    });
}

// メッセージ表示
function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `favorite-message ${type}`;
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        messageDiv.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(messageDiv);
        }, 300);
    }, 3000);
}

// DOMContentLoaded時の初期化
document.addEventListener("DOMContentLoaded", function() {
    // Dropdown
    var dropdown = document.getElementsByClassName("dropdown");
    var i;

    for (i = 0; i < dropdown.length; i++) {
        dropdown[i].addEventListener("mouseover", function() {
            this.getElementsByClassName("dropdown-content")[0].style.display = "block";
        });
        dropdown[i].addEventListener("mouseout", function() {
            this.getElementsByClassName("dropdown-content")[0].style.display = "none";
        });
    }

    // Lazy Load
    const lazyImages = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.onload = () => img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });

    lazyImages.forEach(img => {
        imageObserver.observe(img);
    });
});

// スマートフォンメニュー外クリック時の処理
document.addEventListener("click", function(e) {
    if (document.getElementById("menu_sp") && document.getElementById("menu_sp").getAttribute("data-isopen") === "true") {
        if (!document.getElementById("menu_sp").contains(e.target) && (!document.getElementById("menu") || !document.getElementById("menu").contains(e.target))) {
            document.getElementById("menu_sp").setAttribute("data-isopen", "false");
        }
    }
});
