SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `s_cards`;
CREATE TABLE `s_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL COMMENT '关联套餐ID',
  `card_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '卡密内容',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0=未使用，1=已使用',
  `used_order_sn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '使用的订单号',
  `used_time` datetime(0) NULL DEFAULT NULL COMMENT '使用时间',
  `ctime` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `plan_id`(`plan_id`) USING BTREE,
  INDEX `status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '卡密表';

SET FOREIGN_KEY_CHECKS = 1;