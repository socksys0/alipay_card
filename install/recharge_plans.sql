/*
 Navicat Premium Data Transfer

 Source Server         : 127.0.0.1
 Source Server Type    : MySQL
 Source Server Version : 50738
 Source Host           : localhost:3306
 Source Schema         : test_cc

 Target Server Type    : MySQL
 Target Server Version : 50738
 File Encoding         : 65001

 Date: 18/07/2026 11:50:07
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for recharge_plans
-- ----------------------------
DROP TABLE IF EXISTS `recharge_plans`;
CREATE TABLE `recharge_plans`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '套餐名称',
  `price` decimal(10, 2) NOT NULL COMMENT '套餐价格(元)',
  `duration_days` int(11) NOT NULL COMMENT '有效天数',
  `mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'user' COMMENT '模式: user-用户模式(更新会员时长), card-卡密模式(提取卡密)',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '套餐描述',
  `sort_order` int(11) NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态: 0-禁用, 1-启用',
  `created_at` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `status_sort`(`status`, `sort_order`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '充值套餐表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of recharge_plans
-- ----------------------------

INSERT INTO `recharge_plans` (`name`, `price`, `duration_days`, `mode`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES ('月卡', 10.00, 30, 'user', '30天月卡', 1, 1, NOW(), NOW());
INSERT INTO `recharge_plans` (`name`, `price`, `duration_days`, `mode`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES ('年卡', 300.00, 365, 'card', '充值年卡', 2, 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
