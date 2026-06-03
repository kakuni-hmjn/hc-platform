HC Platform Site v2

assets/ は空です。
以下のPNGを自分で配置してください。

/assets/logo.png
/assets/operator-hero.png

設置先例:
/var/www/html/

URL:
/
/operator/
/login/
/register/

構成:
- parts/header/header.php と parts/header/header.css は同じフォルダ
- parts/footer/footer.php と parts/footer/footer.css は同じフォルダ
- common/ はベースCSS・共通JS
- 各ページフォルダに index.php / ページCSS / ページJS
