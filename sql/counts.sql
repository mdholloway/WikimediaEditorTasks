-- Counter values per user
CREATE TABLE /*_*/wikimedia_editor_tasks_counts (
    -- User's central ID
    wetc_user INTEGER UNSIGNED NOT NULL,
    -- Key ID for the counter
    wetc_key_id INTEGER UNSIGNED NOT NULL,
    -- Optional language code for this count
    wetc_lang VARBINARY(255) NOT NULL,
    -- Counter value
    wetc_count INTEGER UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (wetc_user,wetc_key_id,wetc_lang),
    FOREIGN KEY (wetc_key_id) REFERENCES /*_*/wikimedia_editor_tasks_keys(wet_id)
) /*$wgDBTableOptions*/;
