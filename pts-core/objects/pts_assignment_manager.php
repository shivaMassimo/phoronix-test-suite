<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2009 - 2010, Phoronix Media
	Copyright (C) 2009 - 2010, Michael Larabel

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class pts_assignment_manager
{
	private static $assignments = array();

	public static function set($assignment, &$value)
	{
		self::$assignments[$assignment] = $value;
	}
	public static function set_once($assignment, &$value)
	{
		return !self::is_set($assignment) ? self::set($assignment, $value): false;
	}
	public static function read($assignment)
	{
		return self::is_set($assignment) ? self::$assignments[$assignment] : false;
	}
	public static function is_set($assignment)
	{
		return isset(self::$assignments[$assignment]);
	}
	public static function get_all_assignments($add_assignments = null)
	{
		$current_assignments = self::$assignments;

		if(is_array($add_assignments))
		{
			foreach($add_assignments as $extra_key => $extra_value)
			{
				$current_assignments[$extra_key] = $extra_value;
			}
		}

		return $current_assignments;
	}
	public static function clear($assignment)
	{
		if(self::is_set($assignment))
		{
			unset(self::$assignments[$assignment]);
		}
	}
	public static function clear_all()
	{
		self::$assignments = array();
	}

	/*
	public static function assignment_array_bool_check($assignment_check_array)
	{
		foreach($assignment_check_array as &$assignment)
		{
			if(self::read($assignment))
			{
				return true;
			}
		}

		return false;
	}
	*/
}

?>
