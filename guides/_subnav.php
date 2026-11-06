<?php
/**
 * HC public guide / FAQ section navigation.
 * Scope: public documentation only. Uses no database or authentication.
 */
$hcGuideRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/guides/', PHP_URL_PATH);
$hcGuideRequestPath = is_string($hcGuideRequestPath) && $hcGuideRequestPath !== ''
    ? '/' . trim($hcGuideRequestPath, '/') . '/'
    : '/guides/';
$hcGuideSection = 'guides';
if ($hcGuideRequestPath === '/faq/' || str_starts_with($hcGuideRequestPath, '/faq/')) {
    $hcGuideSection = 'faq';
} elseif (str_starts_with($hcGuideRequestPath, '/guides/minecraft/')) {
    $hcGuideSection = 'minecraft';
} elseif (str_starts_with($hcGuideRequestPath, '/guides/vps/')) {
    $hcGuideSection = 'vps';
} elseif (str_starts_with($hcGuideRequestPath, '/guides/dedicated/')) {
    $hcGuideSection = 'dedicated';
}
$hcGuideNavLinks = [
    ['guides', '/guides/', '技術ガイド'],
    ['minecraft', '/guides/minecraft/', 'Minecraft'],
    ['vps', '/guides/vps/', 'VPS'],
    ['dedicated', '/guides/dedicated/', '専用サーバー'],
    ['faq', '/faq/', 'FAQ'],
];
?>
<nav class="hc-guide-subnav" aria-label="ガイドとFAQ">
<?php foreach ($hcGuideNavLinks as [$section, $url, $label]): ?>
    <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"<?php
        if ($section === $hcGuideSection) {
            echo $url === $hcGuideRequestPath ? ' aria-current="page"' : ' aria-current="location"';
        }
    ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
</nav>
<?php
unset($hcGuideRequestPath, $hcGuideSection, $hcGuideNavLinks, $section, $url, $label);
