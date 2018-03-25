<?php
#classes for creating, interrogating, modifying tree-structured arrays
#Copyright (C) 2018 The CMSMS Dev Team <coreteam@cmsmadesimple.org>
#This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
#This program is free software; you can redistribute it and/or modify
#it under the terms of the GNU General Public License as published by
#the Free Software Foundation; either version 2 of the License, or
#(at your option) any later version.
#
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#You should have received a copy of the GNU General Public License
#along with this program. If not, see <https://www.gnu.org/licenses/>.

namespace CMSMS
{

/**
 * A class for creating and modifying tree-structured arrays
 *
 * @since 2.3
 * @package CMS
 * @license GPL
 */
class CmsArrayTree
{
	const SELFKEY = 'name';
	const PARENTKEY = 'parent';
	const CHILDKEY = 'children';

	/**
	 * @param
	 * @param string $selfkey   $data key-identifier default self::SELFKEY
	 * @param string $parentkey $data key-identifier default self::PARENTKEY
	 * @param string $childkey  $data key-identifier default self::CHILDKEY
	 * @return array
	 */
	public static function create_array($pattern,
		string $selfkey = self::SELFKEY, string $parentkey = self::PARENTKEY, string $childkey = self::CHILDKEY) : array
	{
	}

	/**
	 * Converts flat $data to corresponding tree-form array, using
	 *  modified pre-order tree traversal (a.k.a. nested set)
	 * See http://bytes.schibsted.com/building-tree-structures-in-php-using-references
	 * Node-data for a parent must be present in $data before any child of that parent.
	 * @param array $data      The data to be converted, each member is
	 *  assoc. array with members $selfkey, $parentkey (at least)
	 * @param string $selfkey   $data key-identifier default self::SELFKEY
	 * @param string $parentkey $data key-identifier default self::PARENTKEY
	 * @param string $childkey  $data key-identifier default self::CHILDKEY
	 * @return array
	 */
	public static function load_array(array $data,
		string $selfkey = self::SELFKEY, string $parentkey = self::PARENTKEY, string $childkey = self::CHILDKEY) : array
	{
		$tree = [];
		$references = [];
		foreach ($data as &$node) {
			// Add the node to our associative array
			$references[$node[$selfkey]] = &$node;
			// Add empty placeholder for children
			$node[$childkey] = [];
			if (is_null($node[$parentkey])) {
   				// Add a root-parented node directly to the tree
				$node['path'] = [$node[$selfkey]];
				$tree[$node[$selfkey]] = &$node;
			} else {
		  		// Otherwise, add this node as a reference in its parent
				$parentpath = $references[$node[$parentkey]]['path'];
				$node['path'] = $parentpath + [count($parentpath)=>$node[$selfkey]];
				$references[$node[$parentkey]][$childkey][$node[$selfkey]] = &$node;
			}
			unset($node[$parentkey]);
		}
		unset($node);
		return $tree;
	}

	/**
	 *
	 * @param array $tree       Tree-structured data to process
	 * @param mixed $parentname $tree key-identifier or null for the root.
	 *   Other than for root node, a node with the specified 'name' property must exist.
	 * @param string $parentkey $tree key-identifier default self::PARENTKEY
	 */
	public static function attach_dangles(array &$tree, $parentname,
		string $parentkey = self::PARENTKEY) : void
	{
		//IS THIS POSSIBLE ?
	}

	/**
	 *
	 * @param array $tree       Tree-structured data to process
	 * @param string $parentkey $tree key-identifier default self::PARENTKEY
	 */
	public static function drop_dangles(array &$tree,
		string $parentkey = self::PARENTKEY) : void
	{
		//IS THIS POSSIBLE ?
	}

	/**
	 *
	 * @param array $tree    Tree-structured data to process
	 * @param string $getkey $tree key-identifier
	 * @param mixed $getval  Value to be matched
	 * @param bool  $strict  Optional flag for strict comparison, default true
	 * @return mixed path-array or null if not found
	 */
	public static function find(array $tree, string $getkey, $getval,
		bool $strict = true, string $childkey = self::CHILDKEY)
	{
		$iter = new \RecursiveArrayTreeIterator(
				new \ArrayTreeIterator($tree, 0, $childkey),
				\RecursiveIteratorIterator::SELF_FIRST
				);
		foreach ($iter as $node) {
			if (isset($node[$getkey])) {
				if ($strict && $node[$getkey] === $getval) {
					return $node['path'];
				}
				if (!$strict && $node[$getkey] == $getval) {
					return $node['path'];
				}
			}
		}
	}

	/**
	 * Process $path into clean array
	 *
	 * @param mixed $path array, or ':'-separated string, of node names
	 *  Keys may be 'name'-property strings and/or 0-based children-index ints.
	 *  The first key is for the root node.
	 * @return array of node name strings, or false
	 */
	public static function process_path($path)
	{
		if (is_string($path)) {
			$pathkeys = explode(':',$path);
		} elseif (is_array($path)) {
			$pathkeys = $path;
		} else {
			return false;
		}

		foreach ($pathkeys as &$key) {
			if (is_numeric($key)) {
				$key = (int)$key;
			} else {
				$key = trim($key);
			}
		}
		unset($key);
		return $pathkeys;
	}

	/**
	 * Get the value of $getkey for each node of $tree which is on $path
     *
	 * @param array $tree	  Tree-structured data to process
	 * @param mixed $path	  array, or ':'-separated string, of keys
	 *  Keys may be 'name'-property strings and/or 0-based children-index ints.
	 *  The first key for the root node.
	 * @param string $getkey   $tree key-identifier for nodes in $path
	 * @param mixed $default   Optional value to return if no match found, default null
	 * @param string $childkey $tree key-identifier default self::CHILDKEY
	 * @return array of values, missing values null'd
	 */
	public static function path_get_data(array $tree, $path, string $getkey,
		$default = null, string $childkey = self::CHILDKEY) : array
	{
		$pathkeys = self::process_path($path);
		if (!$pathkeys) {
			return []; //TODO handle error
		}

		$ret = [];
		$name = array_shift($pathkeys);
		$node = $tree[$name];
		foreach ($pathkeys as $key) {
			//TODO support index-keys too
			if (isset($node[$childkey][$key])) {
				$node = $node[$childkey][$key];
			    $ret[] = $node[$getkey] ?? $default;
			} else {
				return []; //error
			}
		}
		return $ret;
	}

	/**
	 * Set/add array-member $setkey=>$setval to each node of $tree which is on $path
	 *
	 * @param array $tree	  tree-structured data to process
	 * @param mixed $path	  array, or ':'-separated string, of keys
	 *  Keys may be 'name'-property strings and/or 0-based children-index ints.
	 *  The first key for the root node.
	 * @param string $setkey   $tree key-identifier
	 * @param mixed $setval	value to be added as $setkey=>$setval in each node of $path
	 * @param string $childkey $tree key-identifier default self::CHILDKEY
	 * @return boolean indicating success
	 */
	public static function path_set_data(array &$tree, $path, string $setkey, $setval,
		string $childkey = self::CHILDKEY) : bool
	{
		$pathkeys = self::process_path($path);
		if (!$pathkeys) {
			return false; //TODO handle error
		}

		$name = array_shift($pathkeys);
		$node = &$tree[$name];
		foreach ($pathkeys as $key) {
			//TODO support index-keys too
			if (isset($node[$childkey][$key])) {
				$node = &$node[$childkey][$key];
			    $node[$setkey] = $setval;
			} else {
				return false; //error
			}
		}
		return true;
	}

	/**
	 *
	 * @param array $tree	  tree-structured data to process
	 * @param mixed $path	  array, or ':'-separated string, of keys
	 *  Keys may be 'name'-property strings and/or 0-based children-index ints.
	 *  The first key for the root node.
	 * @param string $getkey   $tree key-identifier. May be '*' or 'all' or 'node'
	 *  to return the whole node
	 * @param mixed $default   Optional value to return if no match found, default null
	 * @param string $childkey  $data key-identifier default self::CHILDKEY
	 * @return mixed
	 */
	public static function node_get_data(array $tree, $path, string $getkey,
		$default = null, string $childkey = self::CHILDKEY)
	{
		$pathkeys = self::process_path($path);
		if (!$pathkeys) {
			return false; //TODO handle error
		}

		$name = array_shift($pathkeys);
		$node = $tree[$name];
		foreach ($pathkeys as $key) {
			//TODO support index-keys too
			if (isset($node[$childkey][$key])) {
				$node = $node[$childkey][$key];
			} else {
				return false; //error
			}
		}

		if (isset($node[$getkey])) {
			if ($getkey != $childkey) {
				return $node[$getkey];
			}
			$ret = &$node[$getkey];
			return $ret;
		}
		switch ($getkey) {
			case '*':
			case 'all':
			case 'node':
				$ret = &$node;
				return $ret;
		}
		return $default;
	}

	/**
	 *
	 * @param array $tree	  Tree-structured data to process
	 * @param mixed $path	  Array, or ':'-separated string, of keys
	 *  Keys may be 'name'-property strings and/or 0-based children-index ints.
	 *  The first key for the root node.
	 * @param string $setkey   $tree key-identifier
	 * @param mixed $setval	value to be added as $setkey=>$setval in each node of $path
	 * @param string $childkey $tree key-identifier default self::CHILDKEY
	 * @return boolean indicating success
	 */
	public static function node_set_data(array &$tree, $path, string $setkey, $setval,
		string $childkey = self::CHILDKEY) : bool
	{
		$pathkeys = self::process_path($path);
		if (!$pathkeys) {
			return false; //TODO handle error
		}

		$name = array_shift($pathkeys);
		$node = &$tree[$name];
		foreach ($pathkeys as $key) {
			//TODO support index-keys too
			if (isset($node[$childkey][$key])) {
				$node = &$node[$childkey][$key];
			} else {
				return false; //error
			}
		}
		if ($setkey != $childkey) {
		    $node[$setkey] = $setval;
		} else {
			unset($node[$setkey]);
		    $node[$setkey] = $setval;
		}
		return true;
	}
} //class

} //namespace

namespace
{

/**
 * A RecursiveIterator that knows how to recurse into the array tree
 */
class ArrayTreeIterator extends RecursiveArrayIterator implements RecursiveIterator
{
	const CHILDKEY = 'children';
	protected $flags;
	protected $childkey;

	public function __construct($array = [], int $flags = 0, string $childkey = self::CHILDKEY)
	{
		parent::__construct($array, $flags | RecursiveArrayIterator::CHILD_ARRAYS_ONLY);
		$this->flags = $flags;
		$this->childkey = $childkey;
	}

	public function getChildren() : self
	{
		return new static($this->current()[$this->childkey], $this->flags, $this->childkey);
	}

	public function hasChildren() : bool
	{
		return !empty($this->current()[$this->childkey]);
	}
}

/**
 * A RecursiveIterator that supports getting non-leaves only
 * (ParentIterator doesn't support mode)
 */
class RecursiveArrayTreeIterator extends RecursiveIteratorIterator implements OuterIterator
{
	const NONLEAVES_ONLY = 16384;
	protected $noleaves;

	public function __construct(Traversable $iterator, int $mode = RecursiveIteratorIterator::LEAVES_ONLY, int $flags = 0)
	{
		if ($mode & self::NONLEAVES_ONLY) {
            $this->noleaves = true;
            $mode &= ~self::NONLEAVES_ONLY;
		} else {
            $this->noleaves = false;
		}
		parent::__construct($iterator, $mode, $flags);
	}

	public function rewind() : void
	{
		parent::rewind();
		if ($this->noleaves) {
			$this->nextbranch();
		}
	}

	public function next() : void
	{
		parent::next();
		if ($this->noleaves) {
			$this->nextbranch();
		}
	}

	protected function nextbranch() : void
	{
		while ($this->valid() && !$this->getInnerIterator()->hasChildren()) {
			parent::next();
		}
	}
}

} //namespace
