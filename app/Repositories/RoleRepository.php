<?php namespace App\Repositories;

use App\Models\Role;

class RoleRepository {

	/**
	 * The Role instance.
	 *
	 * @var App\Models\Role
	 */
	protected $role;

	/**
	 * Create a new RolegRepository instance.
	 *
	 * @param  App\Models\Role $role
	 * @return void
	 */
	public function __construct(Role $role)
	{
		$this->role = $role;
	}

	/**
	 * Get all roles.
	 *
	 * @return Illuminate\Support\Collection
	 */
	public function all()
	{
		return $this->role->all();
	}

	/**
	 * Get roles collection.
	 *
	 * @param  App\Models\User
	 * @return Array
	 */
	public function getAllSelect()
	{
		$select = $this->all()->pluck('title', 'id');

		return compact('select');
	}
        
        /**
	 * Get roles collection.
	 *
	 * @param  App\Models\User
	 * @return Array
	 */
        

}
