<?php

rex_sql_table::get(rex::getTable('form_studio_form'))->drop();
rex_sql_table::get(rex::getTable('form_studio_submission'))->drop();
