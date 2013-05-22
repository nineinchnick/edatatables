<?php
/**
 * EDTSort class file.
 *
 * @author Jan Was <janek.jan@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2011-2012 Jan Was
 * @license http://www.yiiframework.com/license/
 */

/**
 * EDTSort represents information relevant to sorting.
 * Changes:
 * - added property columns used instead of attributes
 *
 * @see CSort
 */
class EDTSort extends CSort
{
	/**
	 * @var boolean whether the sorting can be applied to multiple attributes simultaneously.
	 * Defaults to false, which means each time the data can only be sorted by one attribute.
	 */
	public $multiSort=true;

	/**
	 * @var array
	 */
	public $columns;
	/**
	 * @var string the name of the GET parameter that specifies which attributes to be sorted
	 * in which direction. Defaults to 'sort'.
	 */
	public $sortVar='iSortingCols';

	/**
	 * @var string prefix for each GET parameter denoting index in $columns property by which to sort
	 */
	public $sortVarIdxPrefix='iSortCol_';

	/**
	 * @var string prefix for each GET parameter denoting direction of sort for a column
	 */
	public $sortVarDirPrefix='sSortDir_';

	private $_directions;

	/**
	 * Constructor.
	 * @param string $modelClass the class name of data models that need to be sorted.
	 *                           This should be a child class of {@link CActiveRecord}.
	 * @param array $columns
	 */
	public function __construct($modelClass=null,$columns=null)
	{
		$this->modelClass=$modelClass;
		$this->columns=$columns;
	}

	/**
	 * Returns the currently requested sort information.
	 * @return array sort directions indexed by attribute names.
	 * The sort direction is true if the corresponding attribute should be
	 * sorted in descending order.
	 */
	public function getDirections()
	{
		if($this->_directions===null)
		{
			$this->_directions=array();
			// treat columns as an indexed array, even if it's associative
			$columns = is_array($this->columns) ? array_values($this->columns) : $this->columns;
			if ( $columns !== null && isset( $_GET[$this->sortVar] ) && ($iSortingCols = intval($_GET[$this->sortVar])) > 0) {
				for ($i = 0; $i < $iSortingCols && isset($_GET[$this->sortVarIdxPrefix.$i]) && isset($columns[intval( $_GET[$this->sortVarIdxPrefix.$i] )]); ++$i) {
					$index = intval($_GET[$this->sortVarIdxPrefix.$i]);
					$column = $columns[$index];
					$attribute = null;
					if (is_string($column) || isset($column['name'])) {
						if (is_string($column)) {
							$params = explode(':',$column);
							if (isset($params[0]))
								$attribute = $params[0];
						} else {
							$attribute = $column['name'];
						}
					} else {
						// checkbox or operations (buttons) column
						//! @todo use FK for checkbox column? need to find it first
					}
					if($attribute !== null && ($this->resolveAttribute($attribute))!==false) {
						$descending = isset($_GET[$this->sortVarDirPrefix.$i]) && $_GET[$this->sortVarDirPrefix.$i] == "desc";
						$this->_directions[$attribute]=$descending;
						if(!$this->multiSort)
							return $this->_directions;
					}
				}
			}
			if($this->_directions===array() && is_array($this->defaultOrder))
				$this->_directions=$this->defaultOrder;
		}
		return $this->_directions;
	}
}
