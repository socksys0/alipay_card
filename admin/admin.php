<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
require_once __DIR__ . "/../conn/db_conn.php";
require_once __DIR__ . "/../func.php";

$admin_config = get_admin_config();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$config_groups = array(
    'alipay' => '支付宝配置',
    'contact' => '联系方式',
    'system' => '系统配置'
);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>个人收款卡密管理系统</title>
    <link rel="stylesheet" href="/src/layui/css/layui.css">
    <style>
        body {
            background-color: #f5f7fa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .layui-card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }
        .layui-card-header {
            background-color: #fafbfc;
            border-bottom: 1px solid #e8ecf0;
        }
        .layui-tab-title li {
            border-radius: 6px 6px 0 0;
        }
        .layui-tab-title .layui-this {
            background-color: #0E7C7B;
            color: #fff;
        }
        .layui-tab-title .layui-this:after {
            display: none;
        }
        .layui-tab-title li:not(.layui-this):hover {
            background-color: #eef4f4;
        }
        .layui-table {
            background-color: #fff;
        }
    </style>
</head>
<body>
<div class="layui-container">
    <div class="layui-row">
        <div class="layui-col-md12">
            <br>
            <div class="layui-card">
                <div class="layui-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>系统配置管理</span>
                    <button type="button" id="logout-btn" class="layui-btn layui-btn-danger layui-btn-sm">退出登录</button>
                </div>
                <div class="layui-card-body">
                    <div class="layui-tab">
                        <ul class="layui-tab-title">
                            <li class="layui-this" data-group="orders">订单管理</li>
                            <li data-group="plans">套餐管理</li>
                            <li data-group="cards">卡密管理</li>
                            <li data-group="contact">联系方式</li>
                            <li data-group="alipay">支付宝配置</li>
                            <li data-group="system">系统配置</li>
                        </ul>
                        <div class="layui-tab-content">
                            <div class="layui-tab-item layui-show" id="tab-orders">
                                <table class="layui-table" id="orders-table"></table>
                            </div>
                            <div class="layui-tab-item" id="tab-plans">
                                <button type="button" id="add-plan-btn" class="layui-btn" style="margin-bottom: 15px;">添加套餐</button>
                                <table class="layui-table" id="plans-table"></table>
                            </div>
                            <div class="layui-tab-item" id="tab-cards">
                                <button type="button" id="add-card-btn" class="layui-btn" style="margin-bottom: 15px;">添加卡密</button>
                                <table class="layui-table" id="cards-table"></table>
                            </div>
                            <div class="layui-tab-item" id="tab-contact">
                                <form class="layui-form" lay-filter="form-contact">
                                    <div id="config-content-contact"></div>
                                    <div class="layui-form-item">
                                        <div class="layui-input-block">
                                            <button type="button" class="layui-btn save-btn" data-group="contact">保存配置</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="layui-tab-item" id="tab-alipay">
                                <form class="layui-form" lay-filter="form-alipay">
                                    <div id="config-content-alipay"></div>
                                    <div class="layui-form-item">
                                        <div class="layui-input-block">
                                            <button type="button" class="layui-btn save-btn" data-group="alipay">保存配置</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="layui-tab-item" id="tab-system">
                                <form class="layui-form" lay-filter="form-system">
                                    <div id="config-content-system"></div>
                                    <div class="layui-form-item">
                                        <div class="layui-input-block">
                                            <button type="button" class="layui-btn save-btn" data-group="system">保存配置</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="plan-modal" style="display: none;">
    <form class="layui-form" lay-filter="plan-form">
        <input type="hidden" name="id">
        <div class="layui-form-item">
            <label class="layui-form-label">套餐名称</label>
            <div class="layui-input-block">
                <input type="text" name="name" lay-verify="required" placeholder="请输入套餐名称" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">价格(元)</label>
            <div class="layui-input-block">
                <input type="number" name="price" lay-verify="required|number" step="0.01" placeholder="请输入价格" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">有效期(天)</label>
            <div class="layui-input-block">
                <input type="number" name="duration_days" lay-verify="required|number" placeholder="请输入天数" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">模式</label>
            <div class="layui-input-block">
                <select name="mode">
                    <option value="user">用户模式</option>
                    <option value="card">卡密模式</option>
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">套餐描述</label>
            <div class="layui-input-block">
                <input type="text" name="description" placeholder="请输入描述" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">排序</label>
            <div class="layui-input-block">
                <input type="number" name="sort_order" lay-verify="number" value="0" class="layui-input">
            </div>
        </div>
    </form>
</div>

<div id="card-modal" style="display: none;">
    <form class="layui-form" lay-filter="card-form">
        <div class="layui-form-item">
            <label class="layui-form-label">关联套餐</label>
            <div class="layui-input-block">
                <select name="plan_id" lay-verify="required" id="card-plan-select">
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">卡密内容</label>
            <div class="layui-input-block">
                <textarea name="card_text" lay-verify="required" placeholder="每行输入一条卡密，支持批量添加" class="layui-textarea" style="height: 200px;"></textarea>
            </div>
        </div>
    </form>
</div>

<script src="/src/layui/layui.js"></script>
<script>
layui.use(['jquery', 'form', 'table'], function() {
    let $ = layui.jquery;
    let form = layui.form;
    let table = layui.table;
    let csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (settings.type.toUpperCase() === 'POST') {
                if (settings.data) {
                    settings.data += '&csrf_token=' + encodeURIComponent(csrfToken);
                } else {
                    settings.data = 'csrf_token=' + encodeURIComponent(csrfToken);
                }
            }
        }
    });

    function loadConfigs(group) {
        $.post('admin_op.php', {action: 'get_configs', group: group}, function(data) {
            if (data.code == 0) {
                let html = '';
                $.each(data.data, function(key, config) {
                    let inputValue = config.value;
                    if (key == 'alipay_merchant_private_key') {
                        inputValue = inputValue.replace(/^-----BEGIN RSA PRIVATE KEY-----\s*/, '');
                        inputValue = inputValue.replace(/\s*-----END RSA PRIVATE KEY-----$/, '');
                        inputValue = inputValue.trim();
                    }
                    if (key == 'admin_password') {
                        inputValue = '';
                    }
                    let inputHtml = '';
                    if (config.input_type == 'textarea') {
                        inputHtml = '<textarea name="' + key + '" class="layui-textarea" placeholder="' + config.desc + '">' + inputValue + '</textarea>';
                    } else if (config.input_type == 'password' || key == 'admin_password') {
                        inputHtml = '<input type="password" name="' + key + '" value="' + inputValue + '" autocomplete="off" placeholder="' + config.desc + '（留空则不修改）" class="layui-input">';
                    } else if (config.input_type == 'text') {
                        inputHtml = '<input type="text" name="' + key + '" value="' + inputValue + '" lay-verify="required" autocomplete="off" placeholder="' + config.desc + '" class="layui-input">';
                    } else if (config.input_type == 'number') {
                        inputHtml = '<input type="number" name="' + key + '" value="' + inputValue + '" lay-verify="required|number" autocomplete="off" placeholder="' + config.desc + '" class="layui-input">';
                    } else {
                        inputHtml = '<input type="text" name="' + key + '" value="' + inputValue + '" lay-verify="required" autocomplete="off" placeholder="' + config.desc + '" class="layui-input">';
                    }
                    html += '<div class="layui-form-item"><label class="layui-form-label">' + config.desc + '</label><div class="layui-input-block">' + inputHtml + '</div></div>';
                });
                $('#config-content-' + group).html(html);
                form.render();
            }
        });
    }

    table.render({
        elem: '#orders-table',
        url: 'admin_op.php',
        where: {action: 'get_orders'},
        method: 'post',
        page: true,
        limit: 10,
        cols: [[
            {field: 'id', title: 'ID', width: 65, sort: true},
            {field: 'order_no', title: '订单号', width: 180, sort: true},
            {field: 'user', title: '用户账号', width: 120, sort: true},
            {field: 'plan_name', title: '套餐', width: 120, templet: function(d) { return d.plan_name || d.order_name || '-'; }},
            {field: 'money', title: '金额', width: 80, sort: true},
            {field: 'plan_mode', title: '模式', width: 100, templet: function(d) { return d.plan_mode == 'card' ? '<span style="color:blue;">卡密模式</span>' : '<span style="color:green;">用户模式</span>'; }},
            {field: 'card_text', title: '卡密', width: 150, templet: function(d) { return d.card_text ? '<span style="color:red;font-family:monospace;">' + d.card_text + '</span>' : '-'; }},
            {field: 'stat', title: '状态', width: 120, templet: function(d) {
                let statText = '';
                let statClass = '';
                switch(d.stat) {
                    case '1': statText = '已支付已发货'; statClass = 'color:green'; break;
                    case '2': statText = '待支付'; statClass = 'color:orange'; break;
                    case '3': statText = '已退款'; statClass = 'color:red'; break;
                    default: statText = '未知'; statClass = 'color:gray';
                }
                return '<span style="' + statClass + '">' + statText + '</span>';
            }},
            {field: 'ctime', title: '下单时间', width: 160, sort: true},
            {title: '操作', width: 120, templet: function(d) {
                return d.stat == 1 ? '<button class="layui-btn layui-btn-xs layui-btn-danger" onclick="refundOrder(' + d.id + ')">标记退款</button>' : '-';
            }}
        ]]
    });

    table.render({
        elem: '#plans-table',
        url: 'admin_op.php',
        where: {action: 'get_plans'},
        method: 'post',
        page: true,
        limit: 10,
        cols: [[
            {field: 'id', title: 'ID', width: 65, sort: true},
            {field: 'name', title: '套餐名称', width: 140, sort: true},
            {field: 'price', title: '价格(元)', width: 100, sort: true},
            {field: 'duration_days', title: '有效期(天)', width: 130, sort: true},
            {field: 'mode', title: '模式', width: 100, templet: function(d) { return d.mode == 'card' ? '卡密模式' : '用户模式'; }},
            {field: 'description', title: '描述', width: 150},
            {field: 'sort_order', title: '排序', width: 80, sort: true},
            {field: 'status', title: '状态', width: 100, templet: function(d) {
                return d.status == 1 
                    ? '<button class="layui-btn layui-btn-xs" onclick="togglePlanStatus(' + d.id + ', 0)">禁用</button>' 
                    : '<button class="layui-btn layui-btn-xs layui-btn-warm" onclick="togglePlanStatus(' + d.id + ', 1)">启用</button>';
            }},
            {title: '操作', width: 180, templet: function(d) {
                return '<button class="layui-btn layui-btn-xs" onclick="editPlan(' + d.id + ')">编辑</button> <button class="layui-btn layui-btn-xs layui-btn-danger" onclick="deletePlan(' + d.id + ')">删除</button>';
            }}
        ]]
    });

    table.render({
        elem: '#cards-table',
        url: 'admin_op.php',
        where: {action: 'get_cards'},
        method: 'post',
        page: true,
        limit: 10,
        cols: [[
            {field: 'id', title: 'ID', width: 65, sort: true},
            {field: 'plan_name', title: '关联套餐', width: 140, sort: true},
            {field: 'card_text', title: '卡密内容', width: 250, templet: function(d) {
                return d.card_text.length > 30 ? d.card_text.substring(0, 30) + '...' : d.card_text;
            }},
            {field: 'status', title: '状态', width: 80, sort: true, templet: function(d) {
                return d.status == 1 ? '<span style="color:green;">已使用</span>' : '<span style="color:orange;">未使用</span>';
            }},
            {field: 'used_order_sn', title: '使用订单号', width: 180, sort: true},
            {field: 'used_time', title: '使用时间', width: 160, sort: true},
            {field: 'ctime', title: '创建时间', width: 160, sort: true},
            {title: '操作', width: 100, templet: function(d) {
                return '<button class="layui-btn layui-btn-xs layui-btn-danger" onclick="deleteCard(' + d.id + ')">删除</button>';
            }}
        ]]
    });

    $('.layui-tab-title li').click(function() {
        let group = $(this).data('group');
        if (group == 'plans') {
            table.reload('plans-table');
        } else if (group == 'cards') {
            table.reload('cards-table');
        } else if (group == 'orders') {
            table.reload('orders-table');
        } else {
            loadConfigs(group);
        }
    });

    loadConfigs('alipay');

    $('.save-btn').click(function() {
        let group = $(this).data('group');
        let formData = $('form[lay-filter="form-' + group + '"]').serializeArray();
        let data = {};
        $.each(formData, function(i, item) {
            data[item.name] = item.value;
        });
        
        if (data.alipay_query_days !== undefined) {
            let days = parseInt(data.alipay_query_days);
            if (isNaN(days) || days < 1 || days > 30) {
                layer.msg('支付宝账单查询天数范围必须在1-30之间', {icon: 5});
                return;
            }
        }
        
        $.post('admin_op.php', {action: 'update_configs', configs: JSON.stringify(data)}, function(res) {
            if (res.code == 0) {
                layer.msg('保存成功', {icon: 1});
            } else {
                layer.msg(res.msg || '保存失败', {icon: 5});
            }
        });
    });

    $('#add-plan-btn').click(function() {
        $('form[lay-filter="plan-form"]')[0].reset();
        $('input[name="id"]').val('');
        $('select[name="mode"]').val('user');
        form.render('select');
        layer.open({
            type: 1,
            title: '添加套餐',
            area: ['450px', '500px'],
            content: $('#plan-modal'),
            btn: ['保存', '取消'],
            yes: function(index) {
                form.verify();
                let formData = $('form[lay-filter="plan-form"]').serializeArray();
                let data = {};
                $.each(formData, function(i, item) {
                    data[item.name] = item.value;
                });
                $.post('admin_op.php', {action: 'add_plan', ...data}, function(res) {
                    if (res.code == 0) {
                        layer.close(index);
                        layer.msg('添加成功', {icon: 1});
                        table.reload('plans-table');
                    } else {
                        layer.msg(res.msg, {icon: 5});
                    }
                });
            }
        });
    });

    $('#add-card-btn').click(function() {
        $('form[lay-filter="card-form"]')[0].reset();
        $.post('admin_op.php', {action: 'get_plans'}, function(data) {
            if (data.code == 0) {
                let options = '<option value="">请选择套餐</option>';
                $.each(data.data, function(i, plan) {
                    if (plan.mode == 'card') {
                        options += '<option value="' + plan.id + '">' + plan.name + '</option>';
                    }
                });
                $('#card-plan-select').html(options);
                form.render('select');
                layer.open({
                    type: 1,
                    title: '添加卡密',
                    area: ['450px', '350px'],
                    content: $('#card-modal'),
                    btn: ['保存', '取消'],
                    yes: function(index) {
                        form.verify();
                        let formData = $('form[lay-filter="card-form"]').serializeArray();
                        let data = {};
                        $.each(formData, function(i, item) {
                            data[item.name] = item.value;
                        });
                        $.post('admin_op.php', {action: 'add_cards', ...data}, function(res) {
                    if (res.code == 0) {
                        layer.close(index);
                        layer.msg(res.msg, {icon: 1});
                        table.reload('cards-table');
                    } else {
                        layer.msg(res.msg, {icon: 5});
                    }
                });
                    }
                });
            }
        });
    });

    window.editPlan = function(id) {
        $.post('admin_op.php', {action: 'get_plans', page: 1, limit: 100}, function(data) {
            if (data.code == 0) {
                let plan = null;
                $.each(data.data, function(i, p) {
                    if (p.id == id) {
                        plan = p;
                        return false;
                    }
                });
                if (plan) {
                    $('input[name="id"]').val(plan.id);
                    $('input[name="name"]').val(plan.name);
                    $('input[name="price"]').val(plan.price);
                    $('input[name="duration_days"]').val(plan.duration_days);
                    $('select[name="mode"]').val(plan.mode || 'user');
                    $('input[name="description"]').val(plan.description);
                    $('input[name="sort_order"]').val(plan.sort_order);
                    form.render('select');
                    layer.open({
                        type: 1,
                        title: '编辑套餐',
                        area: ['450px', '500px'],
                        content: $('#plan-modal'),
                        btn: ['保存', '取消'],
                        yes: function(index) {
                            form.verify();
                            let formData = $('form[lay-filter="plan-form"]').serializeArray();
                            let data = {};
                            $.each(formData, function(i, item) {
                                data[item.name] = item.value;
                            });
                            $.post('admin_op.php', {action: 'edit_plan', ...data}, function(res) {
                                if (res.code == 0) {
                                    layer.close(index);
                                    layer.msg('修改成功', {icon: 1});
                                    table.reload('plans-table');
                                } else {
                                    layer.msg(res.msg, {icon: 5});
                                }
                            });
                        }
                    });
                }
            }
        });
    }

    window.deletePlan = function(id) {
        layer.confirm('确定删除该套餐？', function(index) {
            $.post('admin_op.php', {action: 'delete_plan', id: id}, function(res) {
                layer.close(index);
                if (res.code == 0) {
                    layer.msg('删除成功', {icon: 1});
                    table.reload('plans-table');
                } else {
                    layer.msg(res.msg, {icon: 5});
                }
            });
        });
    }

    window.deleteCard = function(id) {
        layer.confirm('确定删除该卡密？', function(index) {
            $.post('admin_op.php', {action: 'delete_card', id: id}, function(res) {
                layer.close(index);
                if (res.code == 0) {
                    layer.msg('删除成功', {icon: 1});
                    table.reload('cards-table');
                } else {
                    layer.msg(res.msg, {icon: 5});
                }
            });
        });
    }

    window.togglePlanStatus = function(id, status) {
        $.post('admin_op.php', {action: 'toggle_plan_status', id: id, status: status}, function(res) {
            if (res.code == 0) {
                layer.msg('操作成功', {icon: 1});
                table.reload('plans-table');
            } else {
                layer.msg(res.msg, {icon: 5});
            }
        });
    }

    window.refundOrder = function(id) {
        layer.confirm('确定将该订单标记为已退款？', function(index) {
            $.post('admin_op.php', {action: 'update_order_status', id: id, stat: 3}, function(res) {
                layer.close(index);
                if (res.code == 0) {
                    layer.msg('操作成功', {icon: 1});
                    table.reload('orders-table');
                } else {
                    layer.msg(res.msg, {icon: 5});
                }
            });
        });
    }

    $('#logout-btn').click(function() {
        layer.confirm('确定退出登录？', function(index) {
            $.post('admin_op.php', {action: 'logout'}, function(data) {
                layer.close(index);
                window.location.href = 'admin.php';
            });
        });
    });
});
</script>
</body>
</html>