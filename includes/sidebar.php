<?php
// Oturum kontrolü
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    exit; // Sidebar sadece giriş yapmış kullanıcılar için gösterilir
}
?>
<div class="sidebar">
    <div class="sidebar-user">
        <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
        <div class="sidebar-user-role">
            <?php 
            $roleText = 'Ziyaretçi';
            if ($_SESSION['role'] === 'admin') {
                $roleText = 'Yönetici';
            } elseif ($_SESSION['role'] === 'uye') {
                $roleText = 'Üye';
            }
            echo $roleText;
            ?>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php">Kontrol Paneli</a></li>
        <li><a href="upload.php">Dosya Yükle</a></li>
        <li><a href="tools.php">Araçlar</a></li>
        <li><a href="event-calendar.php">Etkinlik Takvimi</a></li>
        <li><a href="announcements.php">Duyurular</a></li>
        <li><a href="gallery.php">Galeri</a></li>
        <li><a href="links.php">Bağlantılar</a></li>
        <li><a href="memories.php">Anılar</a></li>
        <li><a href="games.php">Oyunlar</a></li>
        <li><a href="polls.php">Anketler</a></li>
        <li><a href="profile.php?id=<?php echo $_SESSION['id']; ?>">Profilim</a></li>
        <li><a href="profile_edit.php">Profil Düzenle</a></li>
        <?php if (isset($_SESSION['ai_access']) && $_SESSION['ai_access'] == 1): ?>
        <li><a href="ai_chat.php">AI Sohbet</a></li>
        <?php endif; ?>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <li><a href="dashboard.php?view=users">Kullanıcı Yönetimi</a></li>
        <?php if (isset($_SESSION['ai_access']) && $_SESSION['ai_access'] == 1): ?>
        <li><a href="ai_logs.php">AI Sohbet Logları</a></li>
        <?php endif; ?>
        <?php endif; ?>
        <li><a href="logout.php">Çıkış Yap</a></li>
    </ul>
</div>