<?php
/**
 * 数据库连接配置文件
 * 
 * 说明：
 * - 配置数据库连接参数，定义数据表名称映射
 * - 所有数据库操作都通过此文件建立连接
 * - 修改数据库信息时只需修改此文件
 */

error_reporting(E_ALL ^ E_NOTICE);

/**
 * 数据表名称映射
 * 
 * 使用方式：在SQL语句中引用 $_TB['order'] 代替实际表名
 * 好处：如果表名变更，只需修改此处，无需修改所有SQL
 * 
 * 扩展：如需新增数据表，在此数组中添加新的映射即可
 */
$_TB = array(
    "order" => "s_order",           // 订单表
    "info" => "s_userinfo",          // 用户信息表
    "config" => "system_config",    // 系统配置表
    "plans" => "recharge_plans",     // 充值套餐表
    "cards" => "s_cards"            // 卡密表
);

/**
 * 用户信息表字段映射
 * 
 * 使用方式：在SQL语句中引用 $_FIELD['user'] 代替实际字段名
 * 好处：如果用户表结构变更，只需修改此处，无需修改所有SQL
 * 
 * 扩展：如需映射其他字段，在此数组中添加新的映射即可
 */
$_FIELD = array(
    "user" => "user",               // 用户名字段（登录账号）
    "mupdate" => "mupdate"          // 会员到期时间字段
);

/**
 * 数据库连接参数配置
 * 
 * 修改以下参数以适配您的数据库环境：
 * - db_host: 数据库主机地址
 * - db_name: 数据库名称
 * - db_user: 数据库用户名
 * - db_pass: 数据库密码
 * - db_port: 数据库端口（MySQL默认3306）
 */
$db_host = "localhost";
$db_name = "test_cc";
$db_user = "test_cc";
$db_pass = "mFAKDAYLtm";
$db_port = 3306;

/**
 * PDO数据库连接实例
 * 
 * 使用PDO（PHP Data Objects）进行数据库操作，支持多种数据库驱动
 * 设置 ERRMODE_EXCEPTION 模式，发生错误时抛出异常，便于调试
 * 
 * 使用方式：全局变量 $conn_pdo 可在其他文件中使用
 * 示例：$conn_pdo->prepare($sql)->execute($params);
 */
try {
    $conn_pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}
?>