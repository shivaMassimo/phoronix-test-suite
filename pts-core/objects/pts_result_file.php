<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008 - 2015, Phoronix Media
	Copyright (C) 2008 - 2015, Michael Larabel

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

class pts_result_file
{
	protected $save_identifier = null;
	protected $result_objects = null;
	protected $extra_attributes = null;
	protected $is_multi_way_inverted = false;
	protected $file_location = false;
	private $xml;
	protected $raw_xml;

	public function __construct($result_file)
	{
		$this->save_identifier = $result_file;
		if(!isset($result_file[1024]) && defined('PTS_SAVE_RESULTS_PATH') && is_file(PTS_SAVE_RESULTS_PATH . $result_file . '/composite.xml'))
		{
			$result_file = PTS_SAVE_RESULTS_PATH . $result_file . '/composite.xml';
		}
		$this->extra_attributes = array();

		if(is_file($result_file))
		{
			$this->file_location = $result_file;
			$result_file = file_get_contents($result_file);
		}
		else
		{
			$this->raw_xml = $result_file;
		}

		$xml_options = LIBXML_COMPACT | LIBXML_PARSEHUGE;
		$this->xml = simplexml_load_string($result_file, 'SimpleXMLElement', $xml_options);
	}
	public function validate()
	{
		$dom = new DOMDocument();
		$dom->loadXML($this->getRawXml());
		return $dom->schemaValidate(PTS_OPENBENCHMARKING_PATH . 'schemas/result-file.xsd');
	}
	public function getRawXml()
	{
		if($this->file_location)
		{
			return file_get_contents($this->file_location);
		}

		return $this->raw_xml;
	}
	public function __toString()
	{
		return $this->get_identifier();
	}
	protected static function clean_input($input)
	{
		return strip_tags($input);
	}
	public function sanitize_user_strings($value)
	{
		if(is_array($value))
		{
			return array_map(array($this, 'sanitize_user_strings'), $value);
		}
		else
		{
			return strip_tags($value);
		}
	}
	public static function is_test_result_file($identifier)
	{
		return is_file(PTS_SAVE_RESULTS_PATH . $identifier . '/composite.xml');
	}
	public function get_identifier()
	{
		return $this->save_identifier;
	}
	public function read_extra_attribute($key)
	{
		return isset($this->extra_attributes[$key]) ? $this->extra_attributes[$key] : false;
	}
	public function set_extra_attribute($key, $value)
	{
		$this->extra_attributes[$key] = $value;
	}
	public function get_systems()
	{
		$systems = array();
		foreach($this->xml->System as $s)
		{
			$system = new pts_result_file_system(self::clean_input($s->Identifier->__toString()), self::clean_input($s->Hardware->__toString()), self::clean_input($s->Software->__toString()), json_decode(self::clean_input($s->JSON), true), self::clean_input($s->User->__toString()), self::clean_input($s->Notes->__toString()), self::clean_input($s->TimeStamp->__toString()), self::clean_input($s->ClientVersion->__toString()));
			array_push($systems, $system);

		}

		return $systems;
	}
	public function get_system_hardware()
	{
		// XXX this is deprecated
		$hw = array();
		foreach($this->get_systems() as $s)
		{
			array_push($hw, $s->get_hardware());
		}

		return $hw;
	}
	public function get_system_software()
	{
		// XXX this is deprecated
		$sw = array();
		foreach($this->get_systems() as $s)
		{
			array_push($sw, $s->get_software());
		}

		return $sw;
	}
	public function get_system_identifiers()
	{
		// XXX this is deprecated
		$ids = array();
		foreach($this->get_systems() as $s)
		{
			array_push($ids, $s->get_identifier());
		}

		return $ids;
	}
	public function get_system_count()
	{
		// XXX this is deprecated
		return count($this->get_systems());
	}
	public function get_title()
	{
		return $this->sanitize_user_strings($this->xml->Generated->Title);
	}
	public function get_description()
	{
		return $this->sanitize_user_strings($this->xml->Generated->Description);
	}
	public function get_notes()
	{
		return $this->sanitize_user_strings($this->xml->Generated->Notes);
	}
	public function get_internal_tags()
	{
		return $this->sanitize_user_strings($this->xml->Generated->InternalTags);
	}
	public function get_reference_id()
	{
		return $this->sanitize_user_strings($this->xml->Generated->ReferenceID);
	}
	public function get_preset_environment_variables()
	{
		return $this->xml->Generated->PreSetEnvironmentVariables;
	}
	public function get_test_count()
	{
		return count($this->get_result_objects());
	}
	public function get_contained_tests_hash($raw_output = true)
	{
		$result_object_hashes = $this->get_result_object_hashes();
		sort($result_object_hashes);
		return sha1(implode(',', $result_object_hashes), $raw_output);
	}
	public function get_result_object_hashes()
	{
		$object_hashes = array();

		foreach($this->get_result_objects() as $result_object)
		{
			array_push($object_hashes, $result_object->get_comparison_hash());
		}

		return $object_hashes;
	}
	public function is_results_tracker()
	{
		// If there are more than five results and the only changes in the system identifier names are numeric changes, assume it's a tracker
		// i.e. different dates or different versions of a package being tested

		static $is_tracker = -1;

		if($is_tracker === -1)
		{
			$identifiers = $this->get_system_identifiers();

			if(isset($identifiers[4]))
			{
				// dirty SHA1 hash check
				$is_sha1_hash = strlen($identifiers[0]) == 40 && strpos($identifiers[0], ' ') === false;
				$has_sha1_shorthash = false;

				foreach($identifiers as $i => &$identifier)
				{
					$has_sha1_shorthash = ($i == 0 || $has_sha1_shorthash) && isset($identifier[7]) && pts_strings::string_only_contains(substr($identifier, -8), pts_strings::CHAR_NUMERIC | pts_strings::CHAR_LETTER) && strpos($identifier, ' ') === false;
					$identifier = pts_strings::remove_from_string($identifier, pts_strings::CHAR_NUMERIC | pts_strings::CHAR_DASH | pts_strings::CHAR_DECIMAL);
				}

				$is_tracker = count(array_unique($identifiers)) <= 1 || $is_sha1_hash || $has_sha1_shorthash;

				if($is_tracker)
				{
					$hw = $this->get_system_hardware();

					if(isset($hw[1]) && count($hw) == count(array_unique($hw)))
					{
						// it can't be a results tracker if the hardware is always different
						$is_tracker = false;
					}
				}
			}
			else
			{
				// Definitely not a tracker as not over 5 results
				$is_tracker = false;
			}
		}

		return $is_tracker;
	}
	public function is_multi_way_comparison($identifiers = false, $extra_attributes = null)
	{
		static $is_multi_way = -1;

		if($is_multi_way === -1)
		{
			if(isset($extra_attributes['force_tracking_line_graph']))
			{
				// Phoromatic result tracker
				$is_multi_way = true;
				$this->is_multi_way_inverted = true;
			}
			else
			{
				$hw = null; // XXX: this isn't used anymore at least for now on system hardware
				if($identifiers == false)
				{
					$identifiers = $this->get_system_identifiers();
				}

				$is_multi_way = count($identifiers) < 2 ? false : pts_render::multi_way_identifier_check($identifiers, $hw, $this);
				$this->is_multi_way_inverted = $is_multi_way && $is_multi_way[1];
			}
		}

		return $is_multi_way;
	}
	public function invert_multi_way_invert()
	{
		$this->is_multi_way_inverted = ($this->is_multi_way_inverted == false);
	}
	public function is_multi_way_inverted()
	{
		return $this->is_multi_way_inverted;
	}
	public function get_contained_test_profiles()
	{
		$test_profiles = array();

		foreach($this->get_result_objects() as $object)
		{
			array_push($test_profiles, $object->test_profile);
		}

		return $test_profiles;
	}
	public function override_result_objects($result_objects)
	{
		$this->result_objects = $result_objects;
	}
	protected function get_result_object(&$result, $read_only_objects = false)
	{
		$test_profile = new pts_test_profile(($result->Identifier != null ? $result->Identifier->__toString() : null), null, !$read_only_objects);
		$test_profile->set_test_title($result->Title->__toString());
		$test_profile->set_version($result->AppVersion->__toString());
		$test_profile->set_result_scale($result->Scale->__toString());
		$test_profile->set_result_proportion($result->Proportion->__toString());
		$test_profile->set_display_format($result->DisplayFormat->__toString());

		$test_result = new pts_test_result($test_profile);
		$test_result->set_used_arguments_description($result->Description->__toString());
		$test_result->set_used_arguments($result->Arguments->__toString());

		$result_buffer = new pts_test_result_buffer();
		foreach($result->Data->Entry as $entry)
		{
			$result_buffer->add_test_result($entry->Identifier->__toString(), $entry->Value->__toString(), $entry->RawString->__toString(), (isset($entry->JSON) ? $entry->JSON->__toString() : null));
		}
		$test_result->set_test_result_buffer($result_buffer);

		return $test_result;
	}
	public function get_result_objects($select_indexes = -1, $read_only_objects = false)
	{
		if($this->result_objects == null)
		{
			$this->result_objects = array();

			foreach($this->xml->Result as $result)
			{
				array_push($this->result_objects, $this->get_result_object($result, $read_only_objects));
			}
		}

		if($select_indexes != -1 && $select_indexes !== null)
		{
			$objects = array();

			if($select_indexes == 'ONLY_CHANGED_RESULTS')
			{
				foreach($this->result_objects as &$result)
				{
					// Only show results where the variation was greater than or equal to 1%
					if(abs($result->largest_result_variation(0.01)) >= 0.01)
					{
						array_push($objects, $result);
					}
				}
			}
			else
			{
				foreach(pts_arrays::to_array($select_indexes) as $index)
				{
					if(isset($this->result_objects[$index]))
					{
						array_push($objects, $this->result_objects[$index]);
					}
				}
			}

			return $objects;
		}

		return $this->result_objects;
	}
	public function to_json()
	{
		$file = $this->getRawXml();
		$file = str_replace(array("\n", "\r", "\t"), '', $file);
		$file = trim(str_replace('"', "'", $file));
		$simple_xml = simplexml_load_string($file);
		return json_encode($simple_xml);
	}
}

?>
