<?php
/**
 *
 * @package     {{app_name}}
 *
 * @subpackage  {{table_name}} migration
 *
 * @author      {{developer_name}}<{{developer_email}}>
 * @copyright   2019-{{year}} {{developer_name}}
 * @license     https://opensource.org/licenses/BSD-3-Clause
 *
 * @category    {{app_name}}
 * @see
 *
 * @since       {{date}}
 *
 */

namespace {{namespace}};

use core\database\Migration;

/**
 * {{table_name}} migration class
 */
class {{migration_name}} extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrations use a fluent php writing style.
     *
     * select table name with $this->table('{{table_name}}')
     * add columns with ->addColumn('column_name')->type()->notNull()->default()->comment()
     * add indexes with ->addIndex('index_type', ['column_name'])
     * add foreign keys with ->addForeignKey('column_name', 'foreign_table_name', 'foreign_column_name', 'CASCADE', 'CASCADE')
     *
     * @return void
     */
    public function up()
    {
        $this->table('{{table_name}}')

            ->addColumn('id')->integer()->unsigned()->notNull()->primaryKey()->comment('ID');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->dropTable('{{table_name}}');
    }
}
