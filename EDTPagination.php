<?php

/**
 * EDTPagination class file.
 *
 * @author Jan Was <janek.jan@gmail.com>
 *
 * @link http://www.yiiframework.com/
 *
 * @copyright Copyright &copy; 2011-2012 Jan Was
 * @license http://www.yiiframework.com/license/
 */

/**
 * EDTPagination represents information relevant to pagination.
 *
 * @see CPagination
 */
class EDTPagination extends CPagination
{
    /**
     * The default page size.
     */
    const DEFAULT_PAGE_SIZE = 25;
    /**
     * @var string name of the GET variable storing the current page index. Defaults to 'page'.
     */
    public $pageVar = 'iDisplayStart';

    /**
     * @var string
     */
    public $lengthVar = 'iDisplayLength';

    private $_pageSize = self::DEFAULT_PAGE_SIZE;
    private $_itemCount = 0;
    private $_currentPage;

    /**
     * @return int number of items in each page. Defaults to 10.
     */
    public function getPageSize($recalculate = true)
    {
        if ($this->_pageSize === self::DEFAULT_PAGE_SIZE || $recalculate) {
            if (isset($_GET[$this->lengthVar])) {
                if (($this->_pageSize = (int) $_GET[$this->lengthVar]) <= 0) {
                    $this->_pageSize = self::DEFAULT_PAGE_SIZE;
                }
            } else {
                $this->_pageSize = self::DEFAULT_PAGE_SIZE;
            }
        }

        return $this->_pageSize;
    }

    /**
     * @param int $value number of items in each page
     */
    public function setPageSize($value)
    {
        if (($this->_pageSize = $value) <= 0) {
            $this->_pageSize = self::DEFAULT_PAGE_SIZE;
        }
        $_GET[$this->lengthVar] = $this->_pageSize;
    }

    /**
     * @return int total number of items. Defaults to 0.
     */
    public function getItemCount()
    {
        return $this->_itemCount;
    }

    /**
     * @param int $value total number of items.
     */
    public function setItemCount($value)
    {
        if (($this->_itemCount = $value) < 0) {
            $this->_itemCount = 0;
        }
    }

    /**
     * @param bool $recalculate whether to recalculate the current page based on the page size and item count.
     *
     * @return int the zero-based index of the current page. Defaults to 0.
     */
    public function getCurrentPage($recalculate = true)
    {
        if ($this->_currentPage === null || $recalculate) {
            if (isset($_GET[$this->pageVar])) {
                $this->_currentPage = floor(intval($_GET[$this->pageVar]) / $this->getPageSize());
                if ($this->validateCurrentPage) {
                    $pageCount = $this->getPageCount();
                    if ($this->_currentPage >= $pageCount) {
                        $this->_currentPage = $pageCount - 1;
                    }
                }
                if ($this->_currentPage < 0) {
                    $this->_currentPage = 0;
                }
            } else {
                $this->_currentPage = 0;
            }
        }

        return $this->_currentPage;
    }

    /**
     * @param int $value the zero-based index of the current page.
     */
    public function setCurrentPage($value)
    {
        $this->_currentPage = $value;
        $_GET[$this->pageVar] = $value + 1;
    }

    /**
     * @return int number of pages
     */
    public function getPageCount()
    {
        return (int) (($this->_itemCount + $this->_pageSize - 1) / $this->_pageSize);
    }

    /**
     * Creates the URL suitable for pagination.
     * This method is mainly called by pagers when creating URLs used to
     * perform pagination. The default implementation is to call
     * the controller's createUrl method with the page information.
     * You may override this method if your URL scheme is not the same as
     * the one supported by the controller's createUrl method.
     *
     * @param CController $controller the controller that will create the actual URL
     * @param int         $page       the page that the URL should point to. This is a zero-based index.
     *
     * @return string the created URL
     */
    public function createPageUrl($controller, $page)
    {
        $params = $this->params === null ? $_GET : $this->params;
        $params[$this->pageVar] = $page * $this->getPageSize(true);

        if (isset($params[$this->lengthVar]) && $params[$this->lengthVar] === self::DEFAULT_PAGE_SIZE) {
            unset($params[$this->lengthVar]);
        }

        return $controller->createUrl($this->route, $params);
    }
}
