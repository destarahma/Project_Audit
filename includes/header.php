<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <!-- Font Awesome for professional icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon-wrapper">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="brand-text">
                    <div class="brand-department">Departemen Procurement</div>
                    <h2>AUDIT</h2>
                    <span class="brand-subtitle">Management Digital</span>
                </div>
            </div>
            
            <nav class="sidebar-menu">
                <a href="<?php echo BASE_URL; ?>index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <i class="fas fa-home"></i>
                    </span>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>audit/select_type.php" class="menu-item <?php echo (strpos($_SERVER['PHP_SELF'], 'create.php') !== false || strpos($_SERVER['PHP_SELF'], 'select_type.php') !== false) ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <i class="fas fa-plus-circle"></i>
                    </span>
                    <span>Buat Audit</span>
                </a>
                <a href="<?php echo BASE_URL; ?>audit/list.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'list.php') ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <i class="fas fa-list-alt"></i>
                    </span>
                    <span>Daftar Audit</span>
                </a>
                
                <?php if (isAdmin()): ?>
                <div class="menu-divider"></div>
                <div class="menu-section-title">Admin</div>
                
                <a href="<?php echo BASE_URL; ?>admin/templates.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'templates.php' || basename($_SERVER['PHP_SELF']) == 'template_edit.php' || basename($_SERVER['PHP_SELF']) == 'template_view.php') ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <i class="fas fa-file-alt"></i>
                    </span>
                    <span>Template Audit</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/users.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>">
                    <span class="menu-icon">
                        <i class="fas fa-users"></i>
                    </span>
                    <span>Kelola User</span>
                </a>
                <?php endif; ?>
                
                <div class="menu-divider"></div>
                
                <a href="<?php echo BASE_URL; ?>logout.php" class="menu-item menu-logout">
                    <span class="menu-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </span>
                    <span>Logout</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars(getCurrentUser()['full_name']); ?></div>
                        <div class="user-role"><?php echo ucfirst(getCurrentUser()['role']); ?></div>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="content-wrapper">
                <?php 
                $flash = getFlashMessage();
                if ($flash): 
                ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
                <?php endif; ?>
