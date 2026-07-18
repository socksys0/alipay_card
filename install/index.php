<?php
$step = isset($_GET['step']) ? $_GET['step'] : 1;
$install_dir = __DIR__;
$db_config_path = dirname(__DIR__) . '/conn/db_conn.php';

function check_php_version() {
    return version_compare(PHP_VERSION, '7.4.0', '>=');
}

function check_mysql_version($pdo) {
    try {
        $stmt = $pdo->query("SELECT VERSION()");
        $version = $stmt->fetchColumn();
        preg_match('/^\d+\.\d+/', $version, $matches);
        return !empty($matches) && version_compare($matches[0], '5.7', '>=');
    } catch (Exception $e) {
        return false;
    }
}

function load_db_config() {
    $config = array();
    $content = file_get_contents(dirname(__DIR__) . '/conn/db_conn.php');
    if (preg_match('/\$db_host\s*=\s*["\']([^"\']+)["\']/', $content, $matches)) {
        $config['db_host'] = $matches[1];
    }
    if (preg_match('/\$db_name\s*=\s*["\']([^"\']+)["\']/', $content, $matches)) {
        $config['db_name'] = $matches[1];
    }
    if (preg_match('/\$db_user\s*=\s*["\']([^"\']+)["\']/', $content, $matches)) {
        $config['db_user'] = $matches[1];
    }
    if (preg_match('/\$db_pass\s*=\s*["\']([^"\']+)["\']/', $content, $matches)) {
        $config['db_pass'] = $matches[1];
    }
    if (preg_match('/\$db_port\s*=\s*(\d+)/', $content, $matches)) {
        $config['db_port'] = $matches[1];
    }
    return $config;
}

function connect_db($config) {
    try {
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        return false;
    }
}

function table_exists($pdo, $table_name) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table_name'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function execute_sql_file($pdo, $file_path) {
    $sql = file_get_contents($file_path);
    $sql = str_replace('SET FOREIGN_KEY_CHECKS = 0;', '', $sql);
    $sql = str_replace('SET FOREIGN_KEY_CHECKS = 1;', '', $sql);
    $sql = str_replace('SET NAMES utf8mb4;', '', $sql);
    
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = trim($sql);
    
    $statements = preg_split('/;(?=\s*[\r\n]|$)/', $sql);
    
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                return array('success' => false, 'error' => $e->getMessage());
            }
        }
    }
    return array('success' => true);
}

function delete_directory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            delete_directory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

$db_config = load_db_config();
$db_connected = false;
$db_pdo = null;
$installation_locked = false;

if (!empty($db_config['db_host']) && !empty($db_config['db_name']) && !empty($db_config['db_user'])) {
    $db_pdo = connect_db($db_config);
    $db_connected = $db_pdo !== false;
    
    if ($db_connected && table_exists($db_pdo, 'system_config')) {
        $installation_locked = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'import') {
        $sql_files = array(
            'system_config.sql',
            's_userinfo.sql',
            's_order.sql',
            'recharge_plans.sql',
            's_cards.sql'
        );
        
        $results = array();
        foreach ($sql_files as $sql_file) {
            $file_path = $install_dir . '/' . $sql_file;
            if (file_exists($file_path)) {
                $result = execute_sql_file($db_pdo, $file_path);
                $results[$sql_file] = $result;
            } else {
                $results[$sql_file] = array('success' => false, 'error' => '文件不存在');
            }
        }
        
        $all_success = true;
        foreach ($results as $file => $result) {
            if (!$result['success']) {
                $all_success = false;
                break;
            }
        }
        
        if ($all_success) {
            $step = 3;
        } else {
            $step = 2;
            $import_results = $results;
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'delete_install') {
        $deleted = delete_directory($install_dir);
        echo json_encode(array('success' => $deleted));
        exit;
    }
}

$install_exists = file_exists($install_dir);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 · 个人收款卡密管理系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            padding: 30px;
            text-align: center;
            color: #fff;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .step {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step-item {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f0f0;
            color: #999;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            margin: 0 10px;
            position: relative;
        }
        .step-item.active {
            background: #11998e;
            color: #fff;
        }
        .step-item::after {
            content: '';
            position: absolute;
            right: -25px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 2px;
            background: #f0f0f0;
        }
        .step-item:last-child::after {
            display: none;
        }
        .step-item.active::after {
            background: #11998e;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .section-title::before {
            content: '';
            width: 4px;
            height: 16px;
            background: #11998e;
            margin-right: 10px;
            border-radius: 2px;
        }
        .check-list {
            list-style: none;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .check-item:last-child {
            border-bottom: none;
        }
        .check-status {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 12px;
        }
        .check-status.success {
            background: #52c41a;
            color: #fff;
        }
        .check-status.error {
            background: #ff4d4f;
            color: #fff;
        }
        .check-text {
            flex: 1;
            color: #666;
        }
        .check-detail {
            color: #999;
            font-size: 13px;
        }
        .config-box {
            background: #fafafa;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            padding: 20px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            line-height: 1.8;
            color: #555;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .config-path {
            color: #11998e;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 12px 36px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-success {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-group {
            text-align: center;
            margin-top: 30px;
        }
        .btn-group .btn {
            margin: 0 10px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: #f6ffed;
            border: 1px solid #b7eb8f;
            color: #52c41a;
        }
        .alert-error {
            background: #fff2f0;
            border: 1px solid #ffccc7;
            color: #ff4d4f;
        }
        .alert-warning {
            background: #fffbe6;
            border: 1px solid #ffe58f;
            color: #faad14;
        }
        .sql-list {
            list-style: none;
        }
        .sql-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: #fafafa;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .sql-item:last-child {
            margin-bottom: 0;
        }
        .sql-name {
            flex: 1;
            font-weight: 500;
            color: #333;
        }
        .sql-status {
            font-size: 13px;
        }
        .sql-status.success {
            color: #52c41a;
        }
        .sql-status.error {
            color: #ff4d4f;
        }
        .success-icon {
            text-align: center;
            font-size: 64px;
            margin-bottom: 20px;
        }
        .success-title {
            text-align: center;
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        .success-desc {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .security-warning {
            background: #fff2f0;
            border-left: 4px solid #ff4d4f;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-top: 20px;
        }
        .security-warning h4 {
            color: #ff4d4f;
            margin-bottom: 8px;
        }
        .security-warning p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        .lock-icon {
            font-size: 64px;
            text-align: center;
            margin-bottom: 20px;
            color: #ff4d4f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>个人收款卡密管理系统</h1>
            <p>安装向导</p>
        </div>
        
        <div class="content">
            <?php if ($installation_locked): ?>
                <div class="lock-icon">🔒</div>
                <h2 class="success-title" style="color: #ff4d4f;">安装已锁定</h2>
                <p class="success-desc">检测到系统已安装，为防止数据丢失，禁止重新安装。</p>
                
                <div class="alert-warning">
                    <b>注意：</b>如果您确实需要重新安装，请先手动删除数据库中的所有表。
                </div>
                
                <div class="btn-group">
                    <a href="../admin/" class="btn btn-success" target="_blank">进入后台</a>
                    <a href="../" class="btn btn-primary" target="_blank">查看首页</a>
                </div>
            <?php else: ?>
                <div class="step">
                    <div class="step-item <?php echo $step == 1 ? 'active' : ''; ?>">1</div>
                    <div class="step-item <?php echo $step == 2 ? 'active' : ''; ?>">2</div>
                    <div class="step-item <?php echo $step == 3 ? 'active' : ''; ?>">3</div>
                </div>

                <?php if ($step == 1): ?>
                    <div class="section">
                        <h2 class="section-title">环境检测</h2>
                        <ul class="check-list">
                            <li class="check-item">
                                <div class="check-status <?php echo check_php_version() ? 'success' : 'error'; ?>">
                                    <?php echo check_php_version() ? '✓' : '✗'; ?>
                                </div>
                                <div class="check-text">
                                    PHP 版本
                                    <span class="check-detail">当前: <?php echo PHP_VERSION; ?>，要求: 7.4+</span>
                                </div>
                            </li>
                            <li class="check-item">
                                <div class="check-status <?php echo $db_connected ? 'success' : 'error'; ?>">
                                    <?php echo $db_connected ? '✓' : '✗'; ?>
                                </div>
                                <div class="check-text">
                                    数据库连接
                                    <span class="check-detail">
                                        <?php if ($db_connected): ?>
                                            连接成功
                                        <?php elseif (!empty($db_config)): ?>
                                            连接失败，请检查配置
                                        <?php else: ?>
                                            请先配置数据库连接
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </li>
                            <?php if ($db_connected): ?>
                            <li class="check-item">
                                <div class="check-status <?php echo check_mysql_version($db_pdo) ? 'success' : 'error'; ?>">
                                    <?php echo check_mysql_version($db_pdo) ? '✓' : '✗'; ?>
                                </div>
                                <div class="check-text">
                                    MySQL 版本
                                    <span class="check-detail">要求: 5.7+</span>
                                </div>
                            </li>
                            <?php endif; ?>
                            <li class="check-item">
                                <div class="check-status <?php echo is_writable($db_config_path) ? 'success' : 'error'; ?>">
                                    <?php echo is_writable($db_config_path) ? '✓' : '✗'; ?>
                                </div>
                                <div class="check-text">
                                    配置文件可写
                                    <span class="check-detail"><?php echo realpath($db_config_path); ?></span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="section">
                        <h2 class="section-title">数据库配置</h2>
                        <div class="config-box">
                            <span class="config-path">文件路径：</span><?php echo realpath($db_config_path); ?><br><br>
                            <b>请修改以下配置：</b><br><br>
                            $db_host = "<?php echo $db_config['db_host'] ?? 'localhost'; ?>";<br>
                            $db_name = "<?php echo $db_config['db_name'] ?? 'your_database'; ?>";<br>
                            $db_user = "<?php echo $db_config['db_user'] ?? 'your_username'; ?>";<br>
                            $db_pass = "<?php echo $db_config['db_pass'] ?? 'your_password'; ?>";<br>
                            $db_port = <?php echo $db_config['db_port'] ?? '3306'; ?>;
                        </div>
                    </div>

                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="location.reload()" <?php echo !check_php_version() ? 'disabled' : ''; ?>>
                            重新检测
                        </button>
                        <a href="?step=2" class="btn btn-success" <?php echo (!check_php_version() || !$db_connected) ? 'style="opacity:0.6;cursor:not-allowed;"' : ''; ?>
                           <?php echo (!check_php_version() || !$db_connected) ? 'onclick="return false;"' : ''; ?>>
                            进入下一步
                        </a>
                    </div>
                <?php elseif ($step == 2): ?>
                    <?php if (isset($import_results)): ?>
                        <div class="alert <?php echo $all_success ? 'alert-success' : 'alert-error'; ?>">
                            <?php echo $all_success ? '所有SQL文件导入成功！' : '部分SQL文件导入失败，请查看详细信息'; ?>
                        </div>
                        <div class="section">
                            <h2 class="section-title">导入结果</h2>
                            <ul class="sql-list">
                                <?php foreach ($import_results as $file => $result): ?>
                                    <li class="sql-item">
                                        <span class="sql-name"><?php echo $file; ?></span>
                                        <span class="sql-status <?php echo $result['success'] ? 'success' : 'error'; ?>">
                                            <?php echo $result['success'] ? '✓ 成功' : '✗ ' . $result['error']; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="section">
                        <h2 class="section-title">导入数据库</h2>
                        <p style="color: #666; margin-bottom: 20px;">确认数据库配置正确后，点击下方按钮导入SQL文件。导入成功后将自动删除安装目录。</p>
                        
                        <div class="alert-warning" style="margin-bottom: 20px;">
                            <b>注意：</b>导入操作将清空并重新创建所有数据表，请确保数据库为空或已备份数据。
                        </div>

                        <ul class="sql-list">
                            <li class="sql-item">
                                <span class="sql-name">system_config.sql</span>
                                <span class="sql-status success">待导入</span>
                            </li>
                            <li class="sql-item">
                                <span class="sql-name">s_userinfo.sql</span>
                                <span class="sql-status success">待导入</span>
                            </li>
                            <li class="sql-item">
                                <span class="sql-name">s_order.sql</span>
                                <span class="sql-status success">待导入</span>
                            </li>
                            <li class="sql-item">
                                <span class="sql-name">recharge_plans.sql</span>
                                <span class="sql-status success">待导入</span>
                            </li>
                            <li class="sql-item">
                                <span class="sql-name">s_cards.sql</span>
                                <span class="sql-status success">待导入</span>
                            </li>
                        </ul>
                    </div>

                    <div class="btn-group">
                        <a href="?step=1" class="btn btn-primary">返回上一步</a>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="import">
                            <button type="submit" class="btn btn-success" onclick="return confirm('确定要导入SQL文件吗？这将清空并重新创建所有数据表！')">
                                开始导入
                            </button>
                        </form>
                    </div>
                <?php elseif ($step == 3): ?>
                    <div class="success-icon">🎉</div>
                    <h2 class="success-title">安装成功！</h2>
                    <p class="success-desc">个人收款卡密管理系统已安装完成。</p>

                    <div class="alert-success">
                        <b>管理员账号：</b>admin<br>
                        <b>管理员密码：</b>admin<br>
                        <br>
                        <b>后台地址：</b><a href="../admin/" target="_blank">点击进入后台</a>
                    </div>

                    <div class="security-warning">
                        <h4>⚠️ 安全提醒</h4>
                        <?php if ($install_exists): ?>
                            <p>安装目录仍存在，正在尝试自动删除...</p>
                            <p style="font-family: 'Consolas', monospace; margin-top: 10px; color: #ff4d4f;">
                                <?php echo realpath($install_dir); ?>
                            </p>
                            <p id="delete-status" style="margin-top: 10px; color: #faad14;">正在处理中...</p>
                        <?php else: ?>
                            <p>安装目录已自动删除。</p>
                            <p style="font-family: 'Consolas', monospace; margin-top: 10px; color: #52c41a;">
                                ✅ 已删除
                            </p>
                            <p style="margin-top: 10px;">为确保数据安全，请自行检测安装目录是否已彻底删除。</p>
                        <?php endif; ?>
                        <p style="margin-top: 10px;">
                            如果目录仍然存在，请手动删除该目录，以防止安全风险。
                        </p>
                    </div>

                    <div class="btn-group">
                        <a href="../admin/" class="btn btn-success" target="_blank">进入后台</a>
                        <a href="../" class="btn btn-primary" target="_blank">查看首页</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($step == 3 && $install_exists): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    var statusEl = document.getElementById('delete-status');
                    if (xhr.status === 200) {
                        try {
                            var result = JSON.parse(xhr.responseText);
                            if (result.success) {
                                statusEl.innerHTML = '✅ 安装目录已自动删除';
                                statusEl.style.color = '#52c41a';
                            } else {
                                statusEl.innerHTML = '❌ 自动删除失败，请手动删除安装目录';
                                statusEl.style.color = '#ff4d4f';
                            }
                        } catch (e) {
                            statusEl.innerHTML = '❌ 删除结果解析失败，请手动删除安装目录';
                            statusEl.style.color = '#ff4d4f';
                        }
                    } else {
                        statusEl.innerHTML = '❌ 请求失败，请手动删除安装目录';
                        statusEl.style.color = '#ff4d4f';
                    }
                }
            };
            xhr.send('action=delete_install');
        });
    </script>
    <?php endif; ?>
</body>
</html>