<?php
/**
 * 全局公共函数库
 * 
 * 说明：
 * - 存放项目通用的工具函数和配置读取函数
 * - 所有函数均为独立功能，便于复用和维护
 * - 配置读取函数通过数据库统一管理系统配置
 */

/**
 * 判断字符串是否为纯数字
 * 
 * @param string $str 待检测的字符串
 * @return bool 纯数字返回true，否则返回false
 * 
 * 使用场景：验证订单号、用户ID等需要纯数字的字段
 */
function is_num($str) {
    return preg_match('/^[0-9]+$/', $str);
}

/**
 * 验证邮箱格式是否正确
 * 
 * @param string $email 待验证的邮箱地址
 * @return bool 格式正确返回true，否则返回false
 * 
 * 使用场景：验证用户邮箱、收款邮箱等字段
 */
function is_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * JSON响应输出函数
 * 
 * @param array $ret 响应数据数组，包含code（状态码）、msg（提示信息）、data（数据）
 * 
 * 使用场景：所有API接口的统一响应格式
 * 约定：
 * - code = 0: 操作成功
 * - code < 0: 操作失败或参数错误
 * 
 * 示例：rejson(array('code' => 0, 'msg' => '成功', 'data' => $data));
 */
function rejson($ret) {
    header('Content-Type: application/json;charset=UTF-8');
    exit(json_encode($ret, JSON_UNESCAPED_UNICODE));
}

/**
 * 获取单个配置项的值
 * 
 * @param string $key 配置项键名（对应system_config表的config_key字段）
 * @param mixed $default 默认值（当配置项不存在时返回）
 * @return mixed 配置项的值
 * 
 * 使用场景：在代码中获取单个配置，如支付宝app_id、API密钥等
 * 
 * 扩展：如需新增配置项，先在数据库system_config表中添加记录
 *       然后调用此函数获取配置值
 */
function get_config($key, $default = '') {
    global $conn_pdo, $_TB;
    try {
        $sql = "SELECT config_value FROM " . $_TB["config"] . " WHERE config_key = :config_key LIMIT 1";
        $stmt = $conn_pdo->prepare($sql);
        $stmt->execute(array(':config_key' => $key));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['config_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * 获取指定分组的所有配置项
 * 
 * @param string $group 配置分组名称（对应system_config表的config_group字段）
 * @return array 配置项数组，键为config_key，值包含value、desc、input_type
 * 
 * 使用场景：后台配置页面按分组加载配置项
 * 
 * 配置分组说明：
 * - alipay: 支付宝配置（app_id、私钥、公钥等）
 * - recharge: 充值配置（价格、天数等）
 * - contact: 联系方式（邮箱、QQ、微信等）
 * - system: 系统配置（API密钥、管理员账号等）
 */
function get_configs_by_group($group) {
    global $conn_pdo, $_TB;
    try {
        $sql = "SELECT config_key, config_value, config_desc, input_type FROM " . $_TB["config"] . " WHERE config_group = :config_group ORDER BY sort_order";
        $stmt = $conn_pdo->prepare($sql);
        $stmt->execute(array(':config_group' => $group));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $configs = array();
        foreach ($result as $row) {
            $configs[$row['config_key']] = array(
                'value' => $row['config_value'],
                'desc' => $row['config_desc'],
                'input_type' => $row['input_type']
            );
        }
        return $configs;
    } catch (PDOException $e) {
        return array();
    }
}

/**
 * 更新单个配置项
 * 
 * @param string $key 配置项键名
 * @param mixed $value 配置项新值
 * @return bool 更新成功返回true，否则返回false
 * 
 * 使用场景：后台修改单个配置项时调用
 */
function update_config($key, $value) {
    global $conn_pdo, $_TB;
    try {
        $sql = "UPDATE " . $_TB["config"] . " SET config_value = :config_value, updated_at = NOW() WHERE config_key = :config_key";
        $stmt = $conn_pdo->prepare($sql);
        $stmt->execute(array(':config_key' => $key, ':config_value' => $value));
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 批量更新配置项（事务处理）
 * 
 * @param array $configs 配置项数组，键为config_key，值为config_value
 * @return bool 全部更新成功返回true，任一失败回滚并返回false
 * 
 * 使用场景：后台保存整个分组的配置时调用，确保数据一致性
 * 
 * 事务说明：
 * - 使用beginTransaction开启事务
 * - 所有配置更新成功后commit提交
 * - 任一更新失败则rollBack回滚，保证数据一致性
 */
function update_configs($configs) {
    global $conn_pdo, $_TB;
    try {
        $conn_pdo->beginTransaction();
        foreach ($configs as $key => $value) {
            $sql = "UPDATE " . $_TB["config"] . " SET config_value = :config_value, updated_at = NOW() WHERE config_key = :config_key";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':config_key' => $key, ':config_value' => $value));
        }
        $conn_pdo->commit();
        return true;
    } catch (PDOException $e) {
        $conn_pdo->rollBack();
        return false;
    }
}

/**
 * 获取支付宝配置
 * 
 * @return array 支付宝配置数组
 * 
 * 使用场景：调用支付宝API时获取配置参数
 * 
 * 配置项说明：
 * - app_id: 支付宝应用ID
 * - merchant_private_key: 商户私钥
 * - alipay_public_key: 支付宝公钥
 * - query_days: 账单查询天数范围
 */
function get_alipay_config() {
    return array(
        'app_id' => get_config('alipay_app_id', ''),
        'merchant_private_key' => get_config('alipay_merchant_private_key', ''),
        'alipay_public_key' => get_config('alipay_public_key', ''),
        'query_days' => get_config('alipay_query_days', '30')
    );
}

/**
 * 获取联系方式配置
 * 
 * @return array 联系方式配置数组
 * 
 * 使用场景：前端页面显示收款邮箱、联系方式等信息
 */
function get_contact_config() {
    return array(
        'alipay_email' => get_config('alipay_receive_email', ''),
        'info' => get_config('contact_info', '')
    );
}

/**
 * 获取联系方式字符串（用于错误提示）
 * 
 * @return string 格式化的联系方式
 * 
 * 使用场景：错误提示信息中显示联系方式
 */
function get_contact_info() {
    $contact_info = get_config('contact_info', '');
    if (!empty($contact_info)) {
        return "\n" . str_replace("\n", "\n", $contact_info);
    }
    return '';
}

/**
 * 获取API密钥
 * 
 * @return string API密钥
 * 
 * 使用场景：接口请求验证，防止非法请求
 * 
 * 说明：所有API接口（add.php等）需要携带key参数
 *       key值必须与数据库配置的api_key一致
 */
function get_api_key() {
    return get_config('api_key', 'socksys');
}

/**
 * 获取管理员配置
 * 
 * @return array 管理员配置数组（username、password）
 * 
 * 使用场景：后台登录验证
 * 
 * 说明：管理员账号密码存储在数据库中，可在后台修改
 */
function get_admin_config() {
    return array(
        'username' => get_config('admin_username', 'admin'),
        'password' => get_config('admin_password', 'admin123')
    );
}
?>