<?php

use Phinx\Migration\AbstractMigration;

class InstagramProfile extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if (!$this->hasTable('InstagramProfiles')) {
            $table = $this->table('InstagramProfiles', [ 'id' => false ]);
            $table->addColumn('id', 'biginteger', [ 'length' => 20 ])
              ->addColumn('username', 'string')
              ->addColumn('name', 'string')
              ->addColumn('access_token', 'string')
              ->addColumn('profile_picture', 'string', [ 'null' => true, 'default' => null ])
              ->addColumn('bio', 'string', [ 'length' => 1000, 'null' => true, 'default' => null ])
              ->addColumn('website', 'string', [ 'null' => true, 'default' => null ])
              ->addColumn('followers_count', 'integer', [ 'null' => true, 'default' => null ])
              ->addColumn('follows_count', 'integer', [ 'null' => true, 'default' => null ])
              ->addColumn('media_count', 'integer', [ 'null' => true, 'default' => null ])
              ->addColumn('last_refreshed', 'integer')
              ->addColumn('created_at', 'timestamp', ['default' => 0])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->create();
        }
    }

    /**
     * Migrate Up.
     */
    public function up()
    {
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
