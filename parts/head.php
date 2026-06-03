<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'HC Platform', ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription ?? 'HC Platform', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="google-adsense-account" content="ca-pub-6431740810740503">

    <?php if (!empty($enableAdsense) && $enableAdsense === true): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6431740810740503"
            crossorigin="anonymous"></script>
    <?php endif; ?>
    <link rel="icon" type="image/png" href="/assets/fevicon.png">
    <link rel="apple-touch-icon" href="/assets/fevicon.png">

    <link rel="stylesheet" href="/common/base.css">
    <link rel="stylesheet" href="/common/layout.css">
    <link rel="stylesheet" href="/parts/header/header.css">
    <link rel="stylesheet" href="/parts/footer/footer.css">
    <link rel="stylesheet" href="/common/auth.css">

    <?php if (!empty($pageCss)): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($pageCss, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="/common/theme.css">
</head>
