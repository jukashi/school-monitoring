USE school_monitoring;

ALTER TABLE inventory_items
    MODIFY item_type ENUM('uniform','id_card','other') NOT NULL;
