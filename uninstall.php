<?php

rex_sql_table::get(rex::getTable('domain_sso_token'))->drop();
rex_sql_table::get(rex::getTable('domain_sso_session'))->drop();
