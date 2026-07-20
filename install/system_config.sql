SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `system_config`;
CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL COMMENT '配置键名',
  `config_value` text COMMENT '配置值',
  `config_group` varchar(50) NOT NULL COMMENT '配置分组',
  `config_desc` varchar(255) DEFAULT NULL COMMENT '配置描述',
  `input_type` varchar(20) DEFAULT 'text' COMMENT '输入类型:text,textarea,password,number',
  `sort_order` int(11) DEFAULT 0 COMMENT '排序',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

INSERT INTO `system_config` (`config_key`, `config_value`, `config_group`, `config_desc`, `input_type`, `sort_order`) VALUES

('alipay_app_id', '', 'alipay', '支付宝AppID', 'text', 1),
('alipay_merchant_private_key', '', 'alipay', '应用私钥', 'textarea', 2),
('alipay_public_key', '', 'alipay', '支付宝公钥', 'textarea', 3),
('alipay_query_days', '30', 'alipay', '支付宝账单查询天数范围', 'number', 4),

('alipay_receive_email', 'xx-dos@qq.com', 'contact', '支付宝收款邮箱', 'text', 1),
('contact_info', 'QQ: 147777010\n微信: socksys', 'contact', '联系方式', 'textarea', 2),

('api_key', 'socksys', 'system', 'API接口密钥', 'password', 1),
('admin_username', 'admin', 'system', '管理员账号', 'text', 2),
('admin_password', 'admin', 'system', '管理员密码', 'password', 3);

SET FOREIGN_KEY_CHECKS = 1;