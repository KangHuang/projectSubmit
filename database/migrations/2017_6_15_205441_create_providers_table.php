<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProvidersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('providers', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('username', 30)->unique();
			$table->string('email')->unique();
			$table->string('password', 60);
			$table->boolean('seen')->default(false);
			$table->boolean('valid')->default(false);
			$table->boolean('confirmed')->default(false);
			$table->string('confirmation_code')->nullable();
			$table->timestamps();
			$table->rememberToken();			
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('providers');
	}

}
