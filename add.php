<?php
/**
 * 充值业务接口文件
 * 
 * 说明：
 * - 处理充值相关的核心业务逻辑（创建订单、更新用户时长）
 * - 与支付宝API交互验证订单有效性
 * - 通过套餐表(recharge_plans)匹配充值金额和套餐
 * 
 * 接口调用方式：POST请求，携带key参数进行验证
 * 
 * 接口列表：
 * - p=cz: 创建充值订单（核心充值流程）
 * - p=addtime1: 更新用户时长（单独调用时使用）
 * - p=get_plans: 获取可用套餐列表
 */

/**
 * 引入依赖文件
 * 
 * - func.php: 公共函数库（配置读取、JSON响应等）
 * - alipay.php: 支付宝API调用（账单查询等）
 * - db_conn.php: 数据库连接配置
 */
require_once "func.php";
require_once "alipay/alipay.php";
require_once "conn/db_conn.php";

/**
 * 获取POST请求参数
 * 
 * 通过php://input读取原始POST数据，并解析为数组
 * 如果没有数据，返回参数错误
 */
$content = file_get_contents('php://input');
if (!empty($content)) {
    parse_str($content, $post);
} else {
    $r_info = array("code" => -1000, "msg" => "参数错误", "count" => 0, "data" => "");
    rejson($r_info);
}

/**
 * API密钥验证
 * 
 * 所有请求必须携带key参数，与数据库配置的api_key一致
 * 防止非法请求调用接口
 */
$api_key = get_api_key();
if ($_GET['key'] != $api_key) {
    $r_info = array("code" => -1001, "msg" => false, "data" => "参数错误");
    rejson($r_info);
}

/**
 * 请求分发处理
 * 
 * 根据p参数分发到不同的业务逻辑
 */
switch ($post['p']) {
    case "addtime1":
        $remsg = up_order($post);
        rejson($remsg);
        break;
    case "cz":
        $remsg = c_order($post);
        rejson($remsg);
        break;
    case "get_plans":
        $remsg = get_plans();
        rejson($remsg);
        break;
    default:
        $r_info = array("code" => -1002, "msg" => false, "data" => "参数错误");
        rejson($r_info);
}

exit;

/**
 * 根据订单号查询订单信息
 * 
 * @param string $__order_no 支付宝订单号
 * @return array|bool 订单信息数组或false
 */
function get_order($__order_no) {
    global $conn_pdo, $_TB;
    $sql = "select * from " . $_TB["order"] . " where order_no=:order_no ORDER BY id desc LIMIT 1";
    $stmt = $conn_pdo->prepare($sql);
    if (!$stmt) {
        $r_info = array("code" => -2002, "msg" => false, "data" => "数据错误，请刷新页面重试，请联系管理员" . get_contact_info());
        rejson($r_info);
    }
    $data = array(
        ":order_no" => $__order_no
    );
    $stmt->execute($data);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($result)) {
        return $result;
    } else {
        return false;
    }
}

/**
 * 获取所有启用的套餐列表
 * 
 * 返回数据：仅包含启用状态(status=1)的套餐，按排序字段排序
 * 
 * 使用场景：前端充值页面展示套餐供用户选择
 * 
 * 扩展：新增mode字段，用于区分用户模式和卡密模式
 */
function get_plans() {
    global $conn_pdo, $_TB;
    try {
        $sql = "SELECT id, name, price, duration_days, mode, description FROM " . $_TB["plans"] . " WHERE status = 1 ORDER BY sort_order";
        $stmt = $conn_pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array("code" => 0, "data" => $result);
    } catch (PDOException $e) {
        return array("code" => -1, "data" => array());
    }
}

/**
 * 根据金额匹配套餐
 * 
 * @param float $money 充值金额
 * @param int|null $plan_id 套餐ID（可选，指定时优先按ID匹配）
 * @return array|bool 套餐信息数组、歧义错误数组或false
 * 
 * 匹配逻辑：
 * 1. 如果提供了plan_id，优先按ID匹配，并验证价格是否一致
 * 2. 如果未提供plan_id，按金额匹配套餐
 * 3. 如果匹配到多个套餐（金额相同），返回歧义错误，提示用户选择
 * 4. 如果没有匹配到，返回false
 */
function match_plan($money, $plan_id = null) {
    global $conn_pdo, $_TB;
    try {
        // 优先按套餐ID匹配
        if (!empty($plan_id)) {
            $sql = "SELECT * FROM " . $_TB["plans"] . " WHERE id = :id AND status = 1 LIMIT 1";
            $stmt = $conn_pdo->prepare($sql);
            $stmt->execute(array(':id' => $plan_id));
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($plan && $plan['price'] == $money) {
                return $plan;
            }
            return false;
        }
        
        // 按金额匹配套餐
        $sql = "SELECT * FROM " . $_TB["plans"] . " WHERE price = :price AND status = 1 ORDER BY sort_order";
        $stmt = $conn_pdo->prepare($sql);
        $stmt->execute(array(':price' => $money));
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 匹配到唯一套餐
        if (count($plans) == 1) {
            return $plans[0];
        } 
        // 匹配到多个套餐（金额歧义）
        elseif (count($plans) > 1) {
            $plan_names = array();
            foreach ($plans as $p) {
                $plan_names[] = $p['name'];
            }
            return array(
                'error' => 'ambiguous',
                'message' => '金额匹配到多个套餐：' . implode('、', $plan_names) . '，请选择具体套餐',
                'plans' => $plans
            );
        }
        
        // 未匹配到套餐
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 创建充值订单（核心充值流程）
 * 
 * 请求参数：user（用户账号）, order_sn（支付宝订单号）, plan_id（套餐ID，可选）
 * 
 * 流程说明：
 * 1. 验证订单号格式
 * 2. 调用支付宝API查询账单，获取转账金额
 * 3. 根据金额匹配套餐（支持自动匹配和指定套餐ID匹配）
 * 4. 检查订单是否已存在（防止重复充值）
 * 5. 验证用户是否存在
 * 6. 创建订单记录
 * 7. 更新用户时长
 */
function c_order($__post) {
    global $conn_pdo, $_TB;
    
    // 验证订单号格式（纯数字）
    if (!is_num($__post['order_sn'])) {
        $r_info = array("code" => -2002, "msg" => false, "data" => "订单号输入错误，参考请点击蓝色的`?`号");
        rejson($r_info);
    }
    $__post['is_order'] = false;

    // 调用支付宝API查询账单
    $alipay_order = alipay_order_info($__post);
    if ($alipay_order['alipay_data_bill_accountlog_query_response']['code'] == 10000) {
        if ($alipay_order['alipay_data_bill_accountlog_query_response']['total_size'] > 0) {
            // 获取转账金额
            $__post['money'] = (int)$alipay_order['alipay_data_bill_accountlog_query_response']['detail_list'][0]['trans_amount'];
            
            // 匹配套餐
            $plan = match_plan($__post['money'], isset($__post['plan_id']) ? $__post['plan_id'] : null);
            
            // 处理金额歧义（多个套餐价格相同）
            if ($plan && isset($plan['error']) && $plan['error'] == 'ambiguous') {
                $r_info = array("code" => -2004, "msg" => false, "data" => $plan['message'], "plans" => $plan['plans']);
                rejson($r_info);
            }
            
            // 未匹配到有效套餐
            if (!$plan) {
                $plans = get_plans();
                $plan_list = array();
                foreach ($plans['data'] as $p) {
                    $plan_list[] = $p['name'] . '(' . $p['price'] . '元/' . $p['duration_days'] . '天)';
                }
                $r_info = array("code" => -2002, "msg" => false, "data" => "订单金额" . $__post['money'] . "元未匹配到有效套餐，请转账以下金额：" . implode('、', $plan_list) . "，请联系管理员" . get_contact_info());
                rejson($r_info);
            }
            
            // 设置套餐信息
            $__post['plan_id'] = $plan['id'];
            $__post['order_name'] = $plan['name'];
            $__post['duration_days'] = $plan['duration_days'];
            $__post['mode'] = $plan['mode'];
            $__post['is_order'] = true;
        } else {
            $r_info = array("code" => -2002, "msg" => false, "data" => "没有找到订单，请确认订单信息输入是否正确，请联系管理员" . get_contact_info());
            rejson($r_info);
        }
    } else {
        $r_info = array("code" => -2002, "msg" => false, "data" => "账单接口错误，请联系管理员" . get_contact_info());
        rejson($r_info);
    }

    // 检查订单是否已存在
    $info = get_order($__post['order_sn']);
    if ($info) {
        if ($info['stat'] == 1) {
            $order_mode = 'user';
            if ($info['plan_id']) {
                $plan_sql = "SELECT mode FROM " . $_TB["plans"] . " WHERE id = :plan_id LIMIT 1";
                $plan_stmt = $conn_pdo->prepare($plan_sql);
                $plan_stmt->execute(array(':plan_id' => $info['plan_id']));
                $plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
                if ($plan) {
                    $order_mode = $plan['mode'];
                }
            }
            
            if ($order_mode == 'card' && !empty($info['card_text'])) {
                $r_info = array(
                    "code" => 0,
                    "msg" => true,
                    "data" => '之前已提取过【' . ($info['order_name'] ?? '卡密套餐') . '】，您的卡密：' . $info['card_text'],
                    "card_text" => $info['card_text']
                );
                rejson($r_info);
            } else {
                $r_info = array("code" => -2003, "msg" => false, "data" => "该订单已充值，请勿重复充值");
                rejson($r_info);
            }
        } else {
            $r_info = array("code" => -2002, "msg" => false, "data" => "该订单异常，请联系管理员" . get_contact_info());
            rejson($r_info);
        }
    } else {
        $aaa = null;
        $plan_mode = 'user';
        if (!empty($__post['plan_id'])) {
            $mode_sql = "SELECT mode FROM " . $_TB["plans"] . " WHERE id = :plan_id LIMIT 1";
            $mode_stmt = $conn_pdo->prepare($mode_sql);
            $mode_stmt->execute(array(':plan_id' => $__post['plan_id']));
            $mode_result = $mode_stmt->fetch(PDO::FETCH_ASSOC);
            if ($mode_result) {
                $plan_mode = $mode_result['mode'];
            }
        }
        if ($plan_mode != 'card') {
            if (!$aaa = get_user($__post['user'])) {
                $r_info = array("code" => -2002, "msg" => false, "data" => "充值用户不存在");
                rejson($r_info);
            }
        }
        
        // 创建订单记录（根据模式处理）
        $t = date("Y-m-d H:i:s", time());
        $card_text = '';
        
        // 卡密模式：提取一条未使用的卡密
        if ($plan_mode == 'card') {
            $card_sql = "SELECT id, card_text FROM " . $_TB["cards"] . " WHERE plan_id = :plan_id AND status = 0 ORDER BY id ASC LIMIT 1 FOR UPDATE";
            $card_stmt = $conn_pdo->prepare($card_sql);
            $card_stmt->execute(array(':plan_id' => $__post['plan_id']));
            $card = $card_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$card) {
                $r_info = array("code" => -2005, "msg" => false, "data" => "该套餐卡密已用完，请联系管理员" . get_contact_info());
                rejson($r_info);
            }
            
            $card_text = $card['card_text'];
            
            // 更新卡密状态为已使用
            $update_card_sql = "UPDATE " . $_TB["cards"] . " SET status = 1, used_order_sn = :order_no, used_time = :used_time WHERE id = :card_id";
            $update_card_stmt = $conn_pdo->prepare($update_card_sql);
            $update_card_stmt->execute(array(
                ':order_no' => $__post['order_sn'],
                ':used_time' => $t,
                ':card_id' => $card['id']
            ));
        }
        
        $sql = "INSERT INTO " . $_TB["order"] . " (order_no,user,stat,ctime,paytype,money,order_name,plan_id,oldtime,card_text) VALUES (:order_no,:user,:stat,:ctime,:paytype,:money,:order_name,:plan_id,:oldtime,:card_text)";
        $stmt = $conn_pdo->prepare($sql);
        if (!$stmt) {
            $r_info = array("code" => -2002, "msg" => false, "data" => "数据库错误~请联系管理员" . get_contact_info());
            rejson($r_info);
        }
        $data = array(
            ":order_no" => $__post['order_sn'],
            ":user" => ($plan_mode == 'card') ? null : $__post['user'],
            ":stat" => 1,                              // 状态：1=已支付已发货
            ":ctime" => $t,                            // 创建时间
            ":paytype" => 'web',                       // 支付类型
            ":money" => $__post['money'],              // 金额
            ":order_name" => $__post['order_name'],    // 套餐名称
            ":plan_id" => $__post['plan_id'],          // 套餐ID
            ":oldtime" => $aaa ? $aaa['mupdate'] : null,             // 原到期时间
            ":card_text" => $card_text                 // 发放的卡密内容
        );
        $stmt->execute($data);
        
        if ($stmt->rowCount() > 0) {
            // 订单创建成功，根据模式处理后续逻辑
            if ($__post['is_order']) {
                if ($plan_mode == 'card') {
                    // 卡密模式：返回卡密信息
                    $r_info = array(
                        "code" => 0,
                        "msg" => true,
                        "data" => '购买[' . $__post['order_name'] . ']成功！您的卡密：' . $card_text,
                        "card_text" => $card_text
                    );
                    rejson($r_info);
                } else {
                    // 用户模式：更新用户时长
                    $r_info = up_order($__post);
                    rejson($r_info);
                }
            }
        } else {
            $r_info = array("code" => -2002, "msg" => false, "data" => "订单创建失败，请刷新页面重试~");
            rejson($r_info);
        }
    }
}

/**
 * 更新订单状态（标记为已支付）
 * 
 * @param array $__post 订单信息
 * @return bool 更新成功返回true，否则返回false
 */
function up_order_stat($__post) {
    global $conn_pdo, $_TB;
    $val = " set stat=:stat where order_no=:order_no";
    $sql = "update " . $_TB["order"] . " " . $val;
    $stmt = $conn_pdo->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $data = array(
        ":stat" => 1,
        ":order_no" => $__post['order_no'],
    );
    $stmt->execute($data);
    if ($stmt->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

/**
 * 根据用户账号查询用户信息
 * 
 * @param string $__user 用户账号
 * @return array|bool 用户信息数组或false
 */
function get_user($__user) {
    global $conn_pdo, $_TB, $_FIELD;
    $sql = "select id, " . $_FIELD["user"] . " as user, " . $_FIELD["mupdate"] . " as mupdate from " . $_TB["info"] . " where " . $_FIELD["user"] . "=:user ORDER BY id desc LIMIT 1";
    $stmt = $conn_pdo->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $data = array(
        ":user" => $__user
    );
    $stmt->execute($data);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($result)) {
        return $result;
    } else {
        return false;
    }
}

/**
 * 更新用户会员时长
 * 
 * 请求参数：user（用户账号）, duration_days（时长天数）, plan_id（套餐ID，可选）, order_name（套餐名称）
 * 
 * 流程说明：
 * 1. 查询用户信息
 * 2. 获取有效天数（优先使用传入的duration_days，否则从套餐表获取）
 * 3. 计算新的到期时间（在原到期时间基础上累加）
 * 4. 更新用户的mupdate字段
 */
function up_order($__post) {
    global $conn_pdo, $_TB, $_FIELD;
    
    // 查询用户信息
    $user = get_user($__post['user']);
    if (!$user) {
        $msg = array(
            "code" => -203,
            "msg" => false,
            "data" => "没有找到用户信息"
        );
        return $msg;
    }
    
    // 获取有效天数
    $duration_days = $__post['duration_days'];
    if (empty($duration_days) && !empty($__post['plan_id'])) {
        // 如果没有传入天数，从套餐表获取
        $sql = "SELECT duration_days FROM " . $_TB["plans"] . " WHERE id = :plan_id LIMIT 1";
        $stmt = $conn_pdo->prepare($sql);
        $stmt->execute(array(':plan_id' => $__post['plan_id']));
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($plan) {
            $duration_days = $plan['duration_days'];
        }
    }
    
    // 默认31天（兼容旧逻辑）
    if (empty($duration_days)) {
        $duration_days = 31;
    }
    
    // 计算新的到期时间
    $addtime = $duration_days * 24 * 60 * 60;         // 转换为秒
    $sqltime = strtotime($user['mupdate']);            // 获取原到期时间戳
    if ($sqltime < time()) {
        $sqltime = time();                             // 如果已过期，从当前时间开始计算
    }
    $adddate = date("Y-m-d H:i:s", $sqltime + $addtime);  // 新的到期时间

    // 更新用户时长
    $val = " set " . $_FIELD["mupdate"] . "=:mupdate where " . $_FIELD["user"] . "=:user";
    $sql = "update " . $_TB["info"] . " " . $val;
    $stmt = $conn_pdo->prepare($sql);
    if (!$stmt) {
        $msg = array(
            "code" => -202,
            "msg" => false,
            "data" => "数据库错误"
        );
        return $msg;
    }
    $data = array(
        ":mupdate" => $adddate,
        ":user" => $__post['user']
    );
    $stmt->execute($data);
    
    // 查询更新后的用户信息
    $user2 = get_user($__post['user']);
    
    if ($stmt->rowCount() > 0) {
        $msg = array(
            "code" => 0,
            "msg" => true,
            "data" => $__post['user'] . ' 添加[' . $__post['order_name'] . ']，到期时间为[' . $user2["mupdate"] . ']'
        );
        return $msg;
    } else {
        $msg = array(
            "code" => -202,
            "msg" => false,
            "data" => "添加时间失败"
        );
        return $msg;
    }
}
?>