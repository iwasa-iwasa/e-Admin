-- カレンダーカテゴリーラベルテーブルを作成
CREATE TABLE IF NOT EXISTS calendar_category_labels (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    category_key VARCHAR(50) UNIQUE NOT NULL,
    label VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- デフォルト値を挿入
INSERT INTO calendar_category_labels (category_key, label, created_at, updated_at) VALUES
('会議', '会議', NOW(), NOW()),
('業務', '業務', NOW(), NOW()),
('来客', '来客', NOW(), NOW()),
('出張・外出', '出張・外出', NOW(), NOW()),
('休暇', '休暇', NOW(), NOW()),
('その他', 'その他', NOW(), NOW())
ON DUPLICATE KEY UPDATE label = VALUES(label);
