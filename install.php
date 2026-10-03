<?php

/** @var rex_addon $this */

rex_sql_table::get(rex::getTable('domain_sso_token'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('token_hash', 'char(64)'))
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('host', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('next_url', 'text'))
    ->ensureColumn(new rex_sql_column('group_key', 'char(32)'))
    ->ensureColumn(new rex_sql_column('created', 'datetime'))
    ->ensureColumn(new rex_sql_column('expires', 'datetime'))
    ->ensureColumn(new rex_sql_column('used', 'datetime', true))
    ->ensureIndex(new rex_sql_index('token_hash', ['token_hash'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('expires', ['expires']))
    ->ensure();

rex_sql_table::get(rex::getTable('domain_sso_session'))
    ->ensureColumn(new rex_sql_column('session_id', 'varchar(255)'))
    ->setPrimaryKey(['session_id'])
    ->ensureColumn(new rex_sql_column('group_key', 'char(32)'))
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('host', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('created', 'datetime'))
    ->ensureIndex(new rex_sql_index('group_key', ['group_key']))
    ->ensure();
