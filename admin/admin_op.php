<?php
/**
 * 后台管理操作接口文件
 * 
 * 说明：
 * - 处理后台所有AJAX请求（登录、配置管理、套餐管理、订单管理）
 * - 采用RESTful风格设计，通过action参数区分不同操作
 * - 所有接口返回JSON格式数据，状态码约定：code=0成功，code<0失败
 */

/**
 * 引入依赖文件
 * 
 * - func.php: 公共函数库（配置读取、JSON响应等）
 * - db_conn.php: 数据库连接配置
 */
require_once __DIR__ . "/../func.php";
require_once __DIR__ . "/../conn/db_conn.php";

/**
 * 启动会话管理
 * 
 * 使用PHP Session管理登录状态
 * 用户登录成功后设置 $_SESSION['admin_logged_in'] = true
 */
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

/**
 * 获取管理员配置
 * 
 * 用于登录验证，配置存储在数据库中
 */
$admin_config = get_admin_config();

/**
 * 登录状态验证（未登录拦截）
 * 
 * 如果用户未登录，仅允许login操作，其他操作直接退出
 */
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'login') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        if ($username == $admin_config['username'] && $password == $admin_config['password']) {
            $_SESSION['admin_logged_in'] = true;
            rejson(array('code' => 0, 'msg' => '登录成功'));
        } else {
            rejson(array('code' => -1, 'msg' => '账号或密码错误'));
        }
    }
    exit;
}

/**
 * 请求分发处理
 * 
 * 根据action参数分发到不同的处理逻辑
 * 
 * 操作分类：
 * 1. 配置管理：get_configs, update_config, update_configs
 * 2. 套餐管理：get_plans, add_plan, edit_plan, delete_plan, toggle_plan_status
 * 3. 订单管理：get_orders, update_order_status
 * 4. 系统操作：login, logout
 */
if (isset($_POST['action'])) {
    if ($_POST['action'] !== 'login' && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
        rejson(array('code' => -1, 'msg' => '请求无效'));
    }
    
    switch ($_POST['action']) {
        // ========== 配置管理相关 ==========
        
        /**
         * 获取指定分组的配置项
         * 
         * 请求参数：group（配置分组名称）
         * 返回数据：配置项数组
         * 
         * 使用场景：后台配置页面加载配置项
         */
        case 'get_configs':
            $group = $_POST['group'];
            $configs = get_configs_by_group($group);
            rejson(array('code' => 0, 'data' => $configs));
            break;
        
        /**
         * 更新单个配置项
         * 
         * 请求参数：key（配置键名）, value（配置值）
         * 返回结果：操作成功/失败提示
         */
        case 'update_config':
            $key = $_POST['key'];
            $value = $_POST['value'];
            if (update_config($key, $value)) {
                rejson(array('code' => 0, 'msg' => '更新成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '更新失败'));
            }
            break;
        
        /**
         * 批量更新配置项（事务处理）
         * 
         * 请求参数：configs（JSON字符串，配置项键值对数组）
         * 返回结果：操作成功/失败提示
         * 
         * 说明：使用事务保证所有配置同时更新成功，任一失败则回滚
         * 
         * 特殊处理：
         * 1. alipay_query_days：验证范围1-30，超出范围则报错
         * 2. alipay_merchant_private_key：去除前后缀后自动添加标准格式前后缀
         */
        case 'update_configs':
            $configs = json_decode($_POST['configs'], true);
            if (!is_array($configs)) {
                rejson(array('code' => -1, 'msg' => '配置数据格式错误'));
                break;
            }
            
            foreach ($configs as $key => &$value) {
                if ($key == 'alipay_query_days') {
                    $value = (int)$value;
                    if ($value < 1 || $value > 30) {
                        rejson(array('code' => -1, 'msg' => '支付宝账单查询天数范围必须在1-30之间'));
                        break;
                    }
                }
                
                if ($key == 'alipay_merchant_private_key') {
                    $value = trim($value);
                    $value = preg_replace('/^-----BEGIN RSA PRIVATE KEY-----\s*/', '', $value);
                    $value = preg_replace('/\s*-----END RSA PRIVATE KEY-----$/', '', $value);
                    $value = trim($value);
                    $value = "-----BEGIN RSA PRIVATE KEY-----\n" . $value . "\n-----END RSA PRIVATE KEY-----";
                }
                
                if ($key == 'admin_password') {
                    $value = trim($value);
                    if (empty($value)) {
                        unset($configs[$key]);
                    } else {
                        $value = $value;
                    }
                }
            }
            
            if (update_configs($configs)) {
                rejson(array('code' => 0, 'msg' => '保存成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '保存失败'));
            }
            break;
        
        // ========== 套餐管理相关 ==========
        
        /**
         * 返回数据：套餐数组，按排序字段sort_order升序排列（支持分页）
         * 
         * 请求参数：page（页码，默认1）, limit（每页条数，默认10）
         * 返回数据：layui table格式（code, msg, count, data）
         */
        case 'get_plans':
            global $conn_pdo, $_TB;
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
            $offset = ($page - 1) * $limit;
            
            $stmt = $conn_pdo->prepare("SELECT COUNT(*) as total FROM " . $_TB["plans"]);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            $sql = "SELECT * FROM " . $_TB["plans"] . " ORDER BY sort_order LIMIT :offset, :limit";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            rejson(array('code' => 0, 'msg' => '', 'count' => $count, 'data' => $result));
            break;
        
        /**
         * 添加新套餐
         * 
         * 请求参数：name（套餐名称）, price（价格）, duration_days（有效期天数）, 
         *           mode（模式：user/card）, description（描述）, sort_order（排序）
         * 返回结果：操作成功/失败提示
         * 
         * 默认状态：新增套餐默认启用（status=1）
         * 模式说明：user-用户模式(更新会员时长), card-卡密模式(提取卡密)
         */
        case 'add_plan':
            global $conn_pdo, $_TB;
            $name = $_POST['name'];
            $price = $_POST['price'];
            $duration_days = $_POST['duration_days'];
            $mode = $_POST['mode'];
            $description = $_POST['description'];
            $sort_order = $_POST['sort_order'];
            
            $sql = "INSERT INTO " . $_TB["plans"] . " (name, price, duration_days, mode, description, sort_order, status) VALUES (:name, :price, :duration_days, :mode, :description, :sort_order, 1)";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(
                ':name' => $name,
                ':price' => $price,
                ':duration_days' => $duration_days,
                ':mode' => $mode,
                ':description' => $description,
                ':sort_order' => $sort_order
            ));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '添加成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '添加失败'));
            }
            break;
        
        /**
         * 编辑套餐信息
         * 
         * 请求参数：id（套餐ID）及其他套餐字段
         * 返回结果：操作成功/失败提示
         */
        case 'edit_plan':
            global $conn_pdo, $_TB;
            $id = $_POST['id'];
            $name = $_POST['name'];
            $price = $_POST['price'];
            $duration_days = $_POST['duration_days'];
            $mode = $_POST['mode'];
            $description = $_POST['description'];
            $sort_order = $_POST['sort_order'];
            
            $sql = "UPDATE " . $_TB["plans"] . " SET name = :name, price = :price, duration_days = :duration_days, mode = :mode, description = :description, sort_order = :sort_order WHERE id = :id";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(
                ':id' => $id,
                ':name' => $name,
                ':price' => $price,
                ':duration_days' => $duration_days,
                ':mode' => $mode,
                ':description' => $description,
                ':sort_order' => $sort_order
            ));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '修改成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '修改失败'));
            }
            break;
        
        /**
         * 删除套餐
         * 
         * 请求参数：id（套餐ID）
         * 返回结果：操作成功/失败提示
         * 
         * 注意：删除套餐前应检查是否有相关订单引用
         * 扩展：可添加级联删除或软删除逻辑
         */
        case 'delete_plan':
            global $conn_pdo, $_TB;
            $id = $_POST['id'];
            $sql = "DELETE FROM " . $_TB["plans"] . " WHERE id = :id";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':id' => $id));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '删除成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '删除失败'));
            }
            break;
        
        /**
         * 切换套餐状态（启用/禁用）
         * 
         * 请求参数：id（套餐ID）, status（状态值：0禁用，1启用）
         * 返回结果：操作成功/失败提示
         * 
         * 使用场景：控制套餐是否在前端显示
         */
        case 'toggle_plan_status':
            global $conn_pdo, $_TB;
            $id = $_POST['id'];
            $status = $_POST['status'];
            $sql = "UPDATE " . $_TB["plans"] . " SET status = :status WHERE id = :id";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':id' => $id, ':status' => $status));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '操作成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '操作失败'));
            }
            break;
        
        // ========== 订单管理相关 ==========
        
        /**
         * 获取订单列表（关联套餐信息，支持分页）
         * 
         * 请求参数：page（页码，默认1）, limit（每页条数，默认10）
         * 返回数据：layui table格式（code, msg, count, data）
         * 
         * SQL说明：LEFT JOIN关联recharge_plans表获取套餐名称
         *          按下单时间（ctime）降序排列，最新订单在前
         */
        case 'get_orders':
            global $conn_pdo, $_TB;
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
            $offset = ($page - 1) * $limit;
            
            $stmt = $conn_pdo->prepare("SELECT COUNT(*) as total FROM " . $_TB["order"]);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            $sql = "SELECT o.*, p.name as plan_name, p.mode as plan_mode FROM " . $_TB["order"] . " o LEFT JOIN " . $_TB["plans"] . " p ON o.plan_id = p.id ORDER BY o.ctime DESC LIMIT :offset, :limit";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            rejson(array('code' => 0, 'msg' => '', 'count' => $count, 'data' => $result));
            break;
        
        /**
         * 更新订单状态
         * 
         * 请求参数：id（订单ID）, stat（状态值）
         * 返回结果：操作成功/失败提示
         * 
         * 订单状态约定：
         * - 1: 已支付已发货
         * - 2: 待支付
         * - 3: 已退款
         */
        case 'update_order_status':
            global $conn_pdo, $_TB;
            $id = $_POST['id'];
            $stat = $_POST['stat'];
            $sql = "UPDATE " . $_TB["order"] . " SET stat = :stat WHERE id = :id";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':id' => $id, ':stat' => $stat));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '操作成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '操作失败'));
            }
            break;
        
        // ========== 卡密管理相关 ==========
        
        /**
         * 获取卡密列表（支持分页）
         * 
         * 请求参数：page（页码，默认1）, limit（每页条数，默认10）, plan_id（套餐ID，可选）
         * 返回数据：layui table格式（code, msg, count, data）
         * 
         * SQL说明：LEFT JOIN关联recharge_plans表获取套餐名称
         */
        case 'get_cards':
            global $conn_pdo, $_TB;
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
            $offset = ($page - 1) * $limit;
            $plan_id = isset($_POST['plan_id']) && $_POST['plan_id'] ? $_POST['plan_id'] : null;
            
            if ($plan_id) {
                $stmt = $conn_pdo->prepare("SELECT COUNT(*) as total FROM " . $_TB["cards"] . " WHERE plan_id = :plan_id");
                $stmt->execute(array(':plan_id' => $plan_id));
                $count = $stmt->fetchColumn();
                
                $sql = "SELECT c.*, p.name as plan_name FROM " . $_TB["cards"] . " c LEFT JOIN " . $_TB["plans"] . " p ON c.plan_id = p.id WHERE c.plan_id = :plan_id ORDER BY c.ctime DESC LIMIT :offset, :limit";
                $stmt = $conn_pdo->prepare($sql);
                $stmt->bindValue(':plan_id', $plan_id, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $conn_pdo->prepare("SELECT COUNT(*) as total FROM " . $_TB["cards"]);
                $stmt->execute();
                $count = $stmt->fetchColumn();
                
                $sql = "SELECT c.*, p.name as plan_name FROM " . $_TB["cards"] . " c LEFT JOIN " . $_TB["plans"] . " p ON c.plan_id = p.id ORDER BY c.ctime DESC LIMIT :offset, :limit";
                $stmt = $conn_pdo->prepare($sql);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
            }
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            rejson(array('code' => 0, 'msg' => '', 'count' => $count, 'data' => $result));
            break;
        
        /**
         * 添加卡密
         * 
         * 请求参数：plan_id（套餐ID）, card_text（卡密内容，支持多条，每行一条）
         * 返回结果：操作成功/失败提示
         * 
         * 说明：card_text支持多行输入，每行一条卡密，批量添加
         */
        case 'add_cards':
            global $conn_pdo, $_TB;
            $plan_id = isset($_POST['plan_id']) && is_numeric($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
            $card_text = $_POST['card_text'];
            
            if ($plan_id <= 0) {
                rejson(array('code' => -1, 'msg' => '请选择有效的套餐'));
            }
            
            $cards = explode("\n", $card_text);
            $cards = array_filter(array_map('trim', $cards));
            
            if (empty($cards)) {
                rejson(array('code' => -1, 'msg' => '请输入卡密内容'));
            }
            
            $sql = "INSERT INTO " . $_TB["cards"] . " (plan_id, card_text, status) VALUES (:plan_id, :card_text, 0)";
            $stmt = $conn_pdo->prepare($sql);
            
            $count = 0;
            foreach ($cards as $card) {
                if (!empty($card)) {
                    $stmt->execute(array(':plan_id' => $plan_id, ':card_text' => $card));
                    $count++;
                }
            }
            
            rejson(array('code' => 0, 'msg' => '成功添加 ' . $count . ' 条卡密'));
            break;
        
        /**
         * 删除卡密
         * 
         * 请求参数：id（卡密ID）
         * 返回结果：操作成功/失败提示
         */
        case 'delete_card':
            global $conn_pdo, $_TB;
            $id = $_POST['id'];
            $sql = "DELETE FROM " . $_TB["cards"] . " WHERE id = :id";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':id' => $id));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '删除成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '删除失败'));
            }
            break;
        
        /**
         * 获取套餐卡密统计
         * 
         * 请求参数：plan_id（套餐ID）
         * 返回数据：总数量、未使用数量、已使用数量
         */
        case 'get_cards_stats':
            global $conn_pdo, $_TB;
            $plan_id = $_POST['plan_id'];
            
            $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as unused, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as used FROM " . $_TB["cards"] . " WHERE plan_id = :plan_id";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':plan_id' => $plan_id));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            rejson(array('code' => 0, 'data' => $result));
            break;
        
        // ========== 用户管理相关 ==========
        
        /**
         * 获取用户列表（支持分页）
         * 
         * 请求参数：page（页码，默认1）, limit（每页条数，默认10）
         * 返回数据：layui table格式（code, msg, count, data）
         */
        case 'get_users':
            global $conn_pdo, $_TB, $_FIELD;
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
            $offset = ($page - 1) * $limit;
            
            $stmt = $conn_pdo->prepare("SELECT COUNT(*) as total FROM " . $_TB["info"]);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            $sql = "SELECT id, " . $_FIELD["user"] . " as user, " . $_FIELD["mupdate"] . " as mupdate FROM " . $_TB["info"] . " ORDER BY id DESC LIMIT :offset, :limit";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            rejson(array('code' => 0, 'msg' => '', 'count' => $count, 'data' => $result));
            break;
        
        /**
         * 添加用户
         * 
         * 请求参数：user（用户账号）, mupdate（会员到期时间，可选）
         * 返回结果：操作成功/失败提示
         */
        case 'add_user':
            global $conn_pdo, $_TB, $_FIELD;
            $user = $_POST['user'];
            $mupdate = $_POST['mupdate'] ? str_replace('T', ' ', $_POST['mupdate']) : date('Y-m-d H:i:s');
            
            $stmt = $conn_pdo->prepare("SELECT COUNT(*) FROM " . $_TB["info"] . " WHERE " . $_FIELD["user"] . " = :user");
            $stmt->execute(array(':user' => $user));
            if ($stmt->fetchColumn() > 0) {
                rejson(array('code' => -1, 'msg' => '该用户账号已存在'));
            }
            
            $sql = "INSERT INTO " . $_TB["info"] . " (" . $_FIELD["user"] . ", " . $_FIELD["mupdate"] . ") VALUES (:user, :mupdate)";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':user' => $user, ':mupdate' => $mupdate));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '添加成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '添加失败'));
            }
            break;
        
        /**
         * 编辑用户信息
         * 
         * 请求参数：id（用户ID）, user（用户账号）, mupdate（会员到期时间，可选）
         * 返回结果：操作成功/失败提示
         */
        case 'edit_user':
            global $conn_pdo, $_TB, $_FIELD;
            $id = $_POST['id'];
            $user = $_POST['user'];
            $mupdate = $_POST['mupdate'] ? str_replace('T', ' ', $_POST['mupdate']) : null;
            
            $stmt = $conn_pdo->prepare("SELECT COUNT(*) FROM " . $_TB["info"] . " WHERE " . $_FIELD["user"] . " = :user AND id != :id");
            $stmt->execute(array(':user' => $user, ':id' => $id));
            if ($stmt->fetchColumn() > 0) {
                rejson(array('code' => -1, 'msg' => '该用户账号已存在'));
            }
            
            $sql = "UPDATE " . $_TB["info"] . " SET " . $_FIELD["user"] . " = :user, " . $_FIELD["mupdate"] . " = :mupdate WHERE id = :id";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':id' => $id, ':user' => $user, ':mupdate' => $mupdate));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '修改成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '修改失败'));
            }
            break;
        
        /**
         * 删除用户
         * 
         * 请求参数：id（用户ID）
         * 返回结果：操作成功/失败提示
         */
        case 'delete_user':
            global $conn_pdo, $_TB;
            $id = $_POST['id'];
            $sql = "DELETE FROM " . $_TB["info"] . " WHERE id = :id";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':id' => $id));
            if ($stmt->rowCount() > 0) {
                rejson(array('code' => 0, 'msg' => '删除成功'));
            } else {
                rejson(array('code' => -1, 'msg' => '删除失败'));
            }
            break;
        
        // ========== 系统操作相关 ==========
        
        /**
         * 退出登录
         * 
         * 销毁Session，清除登录状态
         */
        case 'logout':
            session_destroy();
            rejson(array('code' => 0, 'msg' => '退出成功'));
            break;
        
        /**
         * 无效操作处理
         * 
         * 当action参数不存在或不匹配时返回错误
         */
        default:
            rejson(array('code' => -1, 'msg' => '无效操作'));
    }
    exit;
}
?>