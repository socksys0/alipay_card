<?php
require_once "conn/db_conn.php";
require_once "func.php";

$id = $_GET['user'];
$contact_config = get_contact_config();

$alipay_email = htmlspecialchars($contact_config['alipay_email'], ENT_QUOTES, 'UTF-8');
$contact_info = nl2br(htmlspecialchars($contact_config['info'], ENT_QUOTES, 'UTF-8'));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title>充值</title>
    
    <link rel="stylesheet" href="/src/layui/css/layui.css"  media="all">
    <link rel="shortcut icon" href="/img/Logo.ico"  /> 
    <link rel="Bookmark" href="/img/Logo.ico"/> 
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .main-card {
            width: 100%;
            max-width: 500px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #fff;
            font-weight: bold;
        }
        .header-title {
            color: #fff;
            font-size: 15px;
            font-weight: 600;
        }
        .header-contact {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
        }
        .tips {
            background: #fffbe6;
            border-bottom: 1px solid #ffe58f;
            padding: 10px 16px;
            font-size: 12px;
            color: #ad8b00;
            line-height: 1.5;
        }
        .tips strong {
            color: #d4a017;
        }
        .tips .email {
            color: #52c41a;
            font-weight: 500;
        }
        .section {
            padding: 16px;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-title::before {
            content: '';
            width: 4px;
            height: 14px;
            background: #ff6b6b;
            border-radius: 2px;
        }
        .plans-container {
            position: relative;
            overflow: hidden;
        }
        .plans-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
            scroll-behavior: smooth;
        }
        .plans-scroll::-webkit-scrollbar {
            display: none;
        }
        .plan-item {
            flex-shrink: 0;
            width: calc((100% - 24px) / 4);
            background: #f8f9fa;
            border: 2px solid transparent;
            border-radius: 10px;
            padding: 10px 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .plan-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.95);
            border: 1px solid #e0e0e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        .plan-arrow img {
            width: 14px;
            height: 14px;
        }
        .plan-arrow:hover {
            background: #fff;
            border-color: #ff6b6b;
        }
        .plan-arrow.left {
            left: -2px;
            display: none;
        }
        .plan-arrow.right {
            right: -2px;
            display: none;
        }
        .plan-arrow.show {
            display: flex;
        }
        .plan-item:hover {
            background: #fff;
            border-color: #ffe4e4;
        }
        .plan-item.selected {
            background: #fff5f5;
            border-color: #ff6b6b;
            box-shadow: 0 2px 8px rgba(255,107,107,0.15);
        }
        .plan-tag {
            position: absolute;
            top: -6px;
            left: 50%;
            transform: translateX(-50%);
            background: #ff6b6b;
            color: #fff;
            font-size: 9px;
            padding: 1px 6px;
            border-radius: 8px;
        }
        .plan-name {
            font-size: 13px;
            color: #333;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .plan-price {
            font-size: 20px;
            color: #ff6b6b;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .plan-price span {
            font-size: 12px;
            font-weight: normal;
        }
        .plan-duration {
            font-size: 11px;
            color: #999;
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group:last-child {
            margin-bottom: 0;
        }
        .form-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
            display: block;
        }
        .form-input {
            width: 100%;
            height: 38px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 0 12px;
            font-size: 13px;
            transition: border-color 0.2s;
            background: #fafafa;
        }
        .form-input:focus {
            outline: none;
            border-color: #ff6b6b;
            background: #fff;
        }
        .form-input.order-sn {
            border: 1.5px dashed #1890ff;
            background: #f0f7ff;
        }
        .form-input.order-sn:focus {
            border-style: solid;
            background: #fff;
        }
        .order-help {
            color: #1890ff;
            font-size: 12px;
            margin-left: 6px;
            text-decoration: none;
        }
        .order-help:hover {
            text-decoration: underline;
        }
        .submit-btn {
            width: 100%;
            height: 42px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 4px;
        }
        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(255,107,107,0.3);
        }
        .submit-btn:active {
            transform: translateY(0);
        }
        .qr-section {
            background: #f8f9fa;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-top: 1px solid #e8e8e8;
        }
        .qr-code {
            width: 80px;
            height: 80px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            flex-shrink: 0;
        }
        .qr-code img {
            width: 100%;
            height: 100%;
        }
        .qr-info {
            flex: 1;
            min-width: 0;
        }
        .qr-price {
            font-size: 22px;
            color: #ff6b6b;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .qr-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
        }
        .pay-methods {
            display: flex;
            gap: 10px;
        }
        .pay-method {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: #888;
            padding: 2px 8px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #e8e8e8;
        }
        .pay-method i {
            width: 16px;
            height: 16px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #fff;
        }
        .pay-method.alipay i {
            background: #1677ff;
        }
        .pay-method.wechat i {
            background: #07c160;
        }
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
<div class="main-card">
    <div class="header">
        <div class="header-left">
            <div class="avatar">VIP</div>
            <div class="header-title">会员充值</div>
        </div>
        <div class="header-contact"><?php echo $contact_info; ?></div>
    </div>
    
    <div class="tips">
        <strong>充值提示：</strong>先下方支付宝扫码或转账到支付宝 <span class="email"><?php echo $alipay_email; ?></span>，转账成功后输入订单号提交
    </div>
    
    <div class="section">
        <div class="section-title">选择套餐</div>
        <div class="plans-container">
            <div class="plan-arrow left" onclick="scrollPlans(-1)"><img src="/img/arrow_left.png" alt="左"></div>
            <div id="plans-list" class="plans-scroll"></div>
            <div class="plan-arrow right" onclick="scrollPlans(1)"><img src="/img/arrow_right.png" alt="右"></div>
        </div>
    </div>
    
    <div class="section" style="padding-top: 0;">
        <div class="form-group" id="user-field">
            <label class="form-label">充值账号</label>
            <input type="text" name="user" id="user" class="form-input order-sn" placeholder="请输入充值账号" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">支付宝订单号 <a href="javascript:void(0)" id="order_help" class="order-help">教程</a></label>
            <input type="text" name="order_sn" id="order_sn" class="form-input order-sn" placeholder="请输入转账订单号">
        </div>
        
        <button type="button" id="submit" class="submit-btn">充值</button>
    </div>
    
    <div class="qr-section">
        <div class="qr-code">
            <img src="../img/zfb.jpg" alt="收款码">
        </div>
        <div class="qr-info">
            <div class="qr-label">扫码支付</div>
            <div class="qr-price" id="qr-price">¥0.00</div>
            <div class="pay-methods">
                <div class="pay-method alipay"><i>支</i>支付宝</div>
                <!--<div class="pay-method wechat"><i>微</i>微信</div>-->
            </div>
        </div>
    </div>
</div>

<div><img id="order_img" style="display: none;width:100%; height:100%;" src="../img/order.jpg"></div>

<script src="/src/layui/layui.js" charset="utf-8"></script>
<script>
layui.use(['jquery', 'form'], function() {
    let $ = layui.jquery;
    let form = layui.form;
    
    let selectedPlan = null;

    function loadPlans() {
        $.post('add.php?key=<?php echo get_api_key(); ?>', {p: 'get_plans'}, function(data) {
            if (data.code == 0) {
                let html = '';
                $.each(data.data, function(i, plan) {
                    let tagHtml = plan.mode == 'card' ? '<div class="plan-tag">卡密</div>' : '';
                    let selectedClass = i === 0 ? ' selected' : '';
                    html += '<div class="plan-item' + selectedClass + '" data-id="' + plan.id + '" data-mode="' + (plan.mode || 'user') + '" data-price="' + plan.price + '" data-name="' + plan.name + '">' +
                        tagHtml +
                        '<div class="plan-name">' + plan.name + '</div>' +
                        '<div class="plan-price"><span>¥</span>' + plan.price + '</div>' +
                        '<div class="plan-duration">' + plan.duration_days + '天</div>' +
                        '</div>';
                });
                $('#plans-list').html(html);
                
                if (data.data.length > 0) {
                    let firstPlan = data.data[0];
                    selectedPlan = {
                        id: firstPlan.id,
                        mode: firstPlan.mode || 'user',
                        price: firstPlan.price,
                        name: firstPlan.name
                    };
                    let btnText = selectedPlan.mode == 'card' ? '提取' : '充值';
                    $('#submit').text(btnText);
                    $('#qr-price').text('¥' + selectedPlan.price);
                    if (selectedPlan.mode == 'card') {
                        $('#user-field').addClass('hidden');
                    }
                }
                
                setTimeout(checkArrows, 100);
            }
        });
    }

    loadPlans();
    
    function checkArrows() {
        let list = $('#plans-list')[0];
        if (!list) return;
        let leftArrow = $('.plan-arrow.left');
        let rightArrow = $('.plan-arrow.right');
        
        let hasScroll = list.scrollWidth > list.clientWidth;
        let isAtStart = list.scrollLeft <= 0;
        let isAtEnd = list.scrollLeft + list.clientWidth >= list.scrollWidth - 1;
        
        if (hasScroll && !isAtEnd) {
            rightArrow.addClass('show');
        } else {
            rightArrow.removeClass('show');
        }
        
        if (hasScroll && !isAtStart) {
            leftArrow.addClass('show');
        } else {
            leftArrow.removeClass('show');
        }
    }
    
    window.scrollPlans = function(direction) {
        let list = $('#plans-list')[0];
        if (!list) return;
        
        let itemWidth = list.querySelector('.plan-item')?.offsetWidth || 100;
        let scrollAmount = (itemWidth + 8) * 4;
        
        if (direction > 0) {
            list.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        } else {
            list.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        }
        
        setTimeout(checkArrows, 300);
    };
    
    $('#plans-list').on('scroll', function() {
        checkArrows();
    });

    $('#plans-list').on('click', '.plan-item', function() {
        $('.plan-item').removeClass('selected');
        $(this).addClass('selected');
        selectedPlan = {
            id: $(this).data('id'),
            mode: $(this).data('mode'),
            price: $(this).data('price'),
            name: $(this).data('name')
        };
        
        let btnText = selectedPlan.mode == 'card' ? '提取' : '充值';
        $('#submit').text(btnText);
        $('#qr-price').text('¥' + selectedPlan.price);
        
        if (selectedPlan.mode == 'card') {
            $('#user-field').addClass('hidden');
        } else {
            $('#user-field').removeClass('hidden');
        }
    });

    $("#submit").click(function(){
        if (!selectedPlan) {
            layer.alert('请先选择套餐', {title: '提示', icon: 5, area: ['280px', 'auto']});
            return;
        }
        
        let orderSn = $('#order_sn').val();
        let btnText = selectedPlan.mode == 'card' ? '提取' : '充值';
        
        if (!orderSn) {
            layer.alert('请输入支付宝订单号', {title: '提示', icon: 5, area: ['280px', 'auto']});
            return;
        }
        if (selectedPlan.mode != 'card' && !$('#user').val()) {
            layer.alert('请输入充值账号', {title: '提示', icon: 5, area: ['280px', 'auto']});
            return;
        }

        let confirmMsg = selectedPlan.mode == 'card' 
            ? '您选择的是【' + selectedPlan.name + '】，请确认已转账 ¥' + selectedPlan.price + '，确认后将提取卡密'
            : '您选择的是【' + selectedPlan.name + '】，请确认已转账 ¥' + selectedPlan.price + '，确认后将自动充值';
        
        layer.confirm(confirmMsg, {
            btn: ['确认' + btnText, '取消'],
            icon: 3,
            area: ['320px', 'auto']
        }, function(index) {
            layer.close(index);
            $.post('add.php?key=<?php echo get_api_key(); ?>', {
                p: 'cz',
                user: $('#user').val(),
                order_sn: orderSn,
                plan_id: selectedPlan.id
            }, function(data) {
                if (data.code == 0) {
                    let content = data.data;
                    if (data.card_text) {
                        content = '<div style="padding:16px;"><p>' + data.data + '</p><div style="margin-top:12px;padding:8px;background:#f0f0f0;border-radius:4px;"><strong>卡密：</strong><p style="font-size:15px;color:red;margin-top:4px;font-family:monospace;">' + data.card_text + '</p></div><p style="font-size:11px;color:#999;margin-top:8px;">请保存好您的卡密</p></div>';
                        layer.open({
                            type: 1,
                            title: btnText + '成功',
                            area: ['360px', '260px'],
                            content: content,
                            btn: ['我知道了'],
                            yes: function(idx) { layer.close(idx); }
                        });
                    } else {
                        layer.alert(data.data, {title: '提交成功', icon: 6, area: ['280px', 'auto']});
                    }
                } else if (data.code == -2004) {
                    let planOptions = '';
                    $.each(data.plans, function(i, p) {
                        planOptions += '<option value="' + p.id + '">' + p.name + '</option>';
                    });
                    layer.open({
                        type: 1,
                        title: '选择套餐',
                        area: ['280px', '220px'],
                        content: '<div style="padding:16px;"><p>' + data.data + '</p><select id="ambiguous_plan" style="width:100%;height:34px;margin-top:8px;border:1px solid #e0e0e0;border-radius:4px;padding:0 8px;">' + planOptions + '</select></div>',
                        btn: ['确认'],
                        yes: function(index) {
                            $.post('add.php?key=<?php echo get_api_key(); ?>', {
                                p: 'cz',
                                user: $('#user').val(),
                                order_sn: orderSn,
                                plan_id: $('#ambiguous_plan').val()
                            }, function(res) {
                                layer.close(index);
                                if (res.code == 0) {
                                    if (res.card_text) {
                                        let content = '<div style="padding:16px;"><p>' + res.data + '</p><div style="margin-top:12px;padding:8px;background:#f0f0f0;border-radius:4px;"><strong>卡密：</strong><p style="font-size:15px;color:red;margin-top:4px;font-family:monospace;">' + res.card_text + '</p></div></div>';
                                        layer.open({
                                            type: 1,
                                            title: '成功',
                                            area: ['360px', '260px'],
                                            content: content,
                                            btn: ['我知道了'],
                                            yes: function(idx) { layer.close(idx); }
                                        });
                                    } else {
                                        layer.alert(res.data, {title: '成功', icon: 6, area: ['280px', 'auto']});
                                    }
                                } else {
                                    layer.alert(res.data, {title: '失败', icon: 5, area: ['280px', 'auto']});
                                }
                            });
                        }
                    });
                } else {
                    layer.alert(data.data, {title: '失败', icon: 5, area: ['280px', 'auto']});
                }
            });
        });
    });

    $("#order_help").click(function(){
        layer.open({
            type: 1,
            title: false,
            closeBtn: 2,
            area: ['280px','500px'],
            skin: 'layui-layer-nobg',
            shadeClose: true,
            content: $('#order_img')
        });
    });
});
</script>
</body>
</html>