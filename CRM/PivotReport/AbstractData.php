<?php

/**
 * Provides a functionality to prepare entity data for Pivot Table.
 */
abstract class CRM_PivotReport_AbstractData implements CRM_PivotReport_DataInterface {
  const ROWS_API_LIMIT = 1000;
  const ROWS_PAGINATED_LIMIT = 10000;
  const ROWS_MULTIVALUES_LIMIT = 1000;
  const ROWS_RETURN_LIMIT = 10000;

  protected $fields = array();
  protected $emptyRow = array();
  protected $multiValues = array();
  protected $formattedValues = array();
  protected $customizedValues = array();

  /**
   * Name of data group.
   *
   * @var string 
   */
  protected $name = NULL;

  public function __construct($name = NULL) {
    $this->name = $name;
  }

  /**
   * Returns an array containing formatted entity data and information
   * needed to make a call for more data.
   *
   * @param array $params
   * @param int $page
   *
   * @return array
   */
  public function get(array $params, $page = 0) {
    $dataSetInstance = new CRM_PivotCache_DataSet($this->name);
    $dataSet = $dataSetInstance->get($page, self::ROWS_RETURN_LIMIT, $params);

    return array(
      array(
      'nextDate' => $dataSet->getNextIndex(),
      'nextPage' => $dataSet->getNextPage(),
      'data' => $dataSet->getData(),
    ));
  }

  /**
   * Rebuilds pivot report cache including header and data.
   *
   * @param array $params
   *
   * @return array
   */
  public function rebuildCache(array $params) {
    $this->fields = $this->getFields();
    $this->emptyRow = $this->getEmptyRow();
    $this->multiValues = array();

    $time = microtime(true);

    $cacheGroup = new CRM_PivotCache_Group($this->name);

    $cacheGroup->clear();

    $count = $this->rebuildData($cacheGroup, $this->name, $params);

    $this->rebuildHeader($cacheGroup, $this->emptyRow);

    return array(
      array(
        'rows' => $count,
        'time' => (microtime(true) - $time),
      )
    );
  }

  /**
   * Rebuilds entity data cache using entity paginated results.
   *
   * @param \CRM_PivotCache_Group $cacheGroup
   * @param string $entityName
   * @param array $params
   * @param int $offset
   * @param int $multiValuesOffset
   * @param int $page
   *
   * @return int
   */
  public function rebuildData($cacheGroup, $entityName, array $params, $offset = 0, $multiValuesOffset = 0, $page = 0) {
    $total = $this->getCount($params);
    $apiParams = $this->getEntityApiParams($params);
    $index = NULL;
    $count = 0;

    while ($offset < $total) {
      if ($offset) {
        $offset--;
      }

      $pages = $this->getPaginatedResults($entityName, $apiParams, $offset, $multiValuesOffset, $page, $index);

      $count += $this->cachePages($cacheGroup, $pages);

      $lastPageIndex = count($pages) - 1;
      $offset = $pages[$lastPageIndex]->getNextOffset();
      $multiValuesOffset = $pages[$lastPageIndex]->getNextMultiValuesOffset();
      $page = $pages[$lastPageIndex]->getPage() + 1;
      $index = $pages[$lastPageIndex]->getIndex();
    }

    return $count;
  }

  /**
   * Rebuilds entity header cache.
   *
   * @param \CRM_PivotCache_Group $cacheGroup
   * @param array $header
   */
  public function rebuildHeader($cacheGroup, array $header) {
    $cacheGroup->cacheHeader($header);
  }

  /**
   * Returns an array of entity data pages.
   *
   * @param string $entityName
   * @param array $apiParams
   * @param int $offset
   * @param int $multiValuesOffset
   * @param int $page
   * @param string $index
   *
   * @return int
   */
  protected function getPaginatedResults($entityName, array $apiParams, $offset = 0, $multiValuesOffset = 0, $page = 0, $index = NULL) {
    $result = array();
    $rowsCount = 0;

    $apiParams['options']['offset'] = $offset;

    $entities = civicrm_api3($entityName, 'get', $apiParams);

    $formattedEntities = $this->formatResult($entities['values']);

    unset($entities);

    while (!empty($formattedEntities)) {
      $split = $this->splitMultiValues($formattedEntities, $offset, $multiValuesOffset);
      $rowsCount += count($split['data']);

      if ($rowsCount > self::ROWS_PAGINATED_LIMIT) {
        break;
      }

      if ($split['info']['index'] !== $index) {
        $page = 0;
        $index = $split['info']['index'];
      }

      $result[] = new CRM_PivotReport_DataPage($split['data'], $index, $page++, $split['info']['nextOffset'], $split['info']['multiValuesOffset']);

      unset($split['data']);

      $formattedEntities = array_slice($formattedEntities, $split['info']['nextOffset'] - $offset, NULL, TRUE);

      $offset = $split['info']['nextOffset'];
      $multiValuesOffset =  $split['info']['multiValuesOffset'];
    }

    return $result;
  }

  /**
   * Puts an array of pages into cache.
   *
   * @param \CRM_PivotCache_Group $cacheGroup
   * @param array $pages
   *
   * @return int
   */
  protected function cachePages($cacheGroup, array $pages) {
    $count = 0;

    foreach ($pages as $page) {
      $count += $cacheGroup->cachePacket($page->getData(), $page->getIndex(), $page->getPage());
    }

    return $count;
  }

  /**
   * Returns an array containing parameters for API 'get' call.
   *
   * @param array $inputParams
   *
   * @return array
   */
  protected function getEntityApiParams(array $inputParams) {
    $params = array(
      'sequential' => 1,
      'return' => implode(',', array_keys($this->fields)),
      'options' => array(
        'limit' => self::ROWS_API_LIMIT,
      ),
    );

    return array_merge($params, $inputParams);
  }

  /**
   * Returns an array containing $data rows and each row containing multiple values
   * of at least one field is populated into separate row for each field's
   * multiple value.
   *
   * @param array $data
   *   Array containing a set of entities
   * @param int $totalOffset
   *   Entity absolute offset we start with
   * @param int $multiValuesOffset
   *   Multi Values offset
   *
   * @return array
   */
  protected function splitMultiValues(array $data, $totalOffset, $multiValuesOffset) {
    $result = array();
    $index = NULL;
    $i = 0;

    foreach ($data as $key => $row) {
      $entityIndexValue = $row['ID'];

      if (!$index) {
        $index = $entityIndexValue;
      }

      if ($index !== $entityIndexValue) {
        $totalOffset--;
        break;
      }

      $multiValuesRows = null;
      if (!empty($this->multiValues[$key])) {
        $multiValuesFields = array_combine($this->multiValues[$key], array_fill(0, count($this->multiValues[$key]), 0));

        $multiValuesRows = $this->populateMultiValuesRow($row, $multiValuesFields, $multiValuesOffset, self::ROWS_MULTIVALUES_LIMIT - $i);

        $result = array_merge($result, $multiValuesRows['data']);
        $multiValuesOffset = 0;
      } else {
        $result[] = array_values($row);
      }
      $i = count($result);

      if ($i === self::ROWS_MULTIVALUES_LIMIT) {
        break;
      }

      unset($this->multiValues[$key]);

      $totalOffset++;
    }

    return array(
      'info' => array(
        'index' => $index,
        'nextOffset' => !empty($multiValuesRows['info']['multiValuesOffset']) ? $totalOffset : $totalOffset + 1,
        'multiValuesOffset' => !empty($multiValuesRows['info']['multiValuesOffset']) ? $multiValuesRows['info']['multiValuesOffset'] : 0,
        'multiValuesTotal' => !empty($multiValuesRows['info']['multiValuesTotal']) ? $multiValuesRows['info']['multiValuesTotal'] : 0,
      ),
      'data' => $result,
    );
  }

  /**
   * Returns an array containing set of rows which are built basing on given $row
   * and $fields array with indexes of multi values of the $row.
   *
   * @param array $row
   *   A single Entity row
   * @param array $fields
   *   Array containing Entity multi value fields as keys and integer
   *   indexes as values
   * @param int $offset
   *   Combination offset to start from
   * @param int $limit
   *   How many records can we generate?
   *
   * @return array
   */
  protected function populateMultiValuesRow(array $row, array $fields, $offset, $limit) {
    $data = array();
    $info = array(
      'multiValuesTotal' => $this->getTotalCombinations($row, $fields),
      'multiValuesOffset' => 0,
    );
    $found = true;
    $i = 0;

    while ($found) {
      if ($i >= $offset) {
        $rowResult = array();
        foreach ($fields as $key => $index) {
          $rowResult[$key] = $row[$key][$index];
        }
        $data[] = array_values(array_merge($row, $rowResult));
      }
      foreach ($fields as $key => $index) {
        $found = false;
        if ($index + 1 === count($row[$key])) {
          $fields[$key] = 0;
          continue;
        }
        $fields[$key]++;
        $found = true;
        break;
      }
      $i++;
      if (($i - $offset === $limit) && $found) {
        $info['multiValuesOffset'] = $i;
        break;
      }
    }

    return array(
      'info' => $info,
      'data' => $data,
    );
  }

  /**
   * Gets number of multivalues combinations for given Entity row.
   *
   * @param array $row
   *   Entity row
   * @param array $fields
   *   Array containing all Entity fields
   *
   * @return int
   */
  protected function getTotalCombinations(array $row, array $fields) {
    $combinations = 1;

    foreach ($fields as $key => $value) {
      if (!empty($row[$key]) && is_array($row[$key])) {
        $combinations *= count($row[$key]);
      }
    }

    return $combinations;
  }

  /**
   * Returns a result of recursively parsed and formatted $data.
   *
   * @param mixed $data
   *   Data element
   * @param string $dataKey
   *   Key of current $data item
   * @param int $level
   *   How deep we are relative to the root of our data
   *
   * @return type
   */
  protected function formatResult($data, $dataKey = null, $level = 0) {
    $result = array();

    if ($level < 2) {
      if ($level === 1) {
        $result = $this->emptyRow;
      }
      $baseKey = $dataKey;
      foreach ($data as $key => $value) {
        if (empty($this->fields[$key]) && $level) {
          continue;
        }
        $dataKey = $key;
        if (!empty($this->fields[$key]['title'])) {
          $key = $this->fields[$key]['title'];
        }
        $result[$key] = $this->formatResult($value, $dataKey, $level + 1);
        if ($level === 1 && is_array($result[$key])) {
          $this->multiValues[$baseKey][] = $key;
        }
      }
    } else {
      return $this->formatValue($dataKey, $data);
    }

    return $result;
  }

  /**
   * Returns $value formatted by available Option Values for the $key Field.
   * If there is no Option Values for the field, then return $value itself
   * with HTML tags stripped.
   * If $value contains an array of values then the method works recursively
   * returning an array of formatted values.
   *
   * @param string $key
   *   Field name
   * @param string $value
   *   Field value
   * @param int $level
   *   Recursion level
   *
   * @return string
   */
  protected function formatValue($key, $value, $level = 0) {
    if (empty($value) || $level > 1) {
      return '';
    }

    $dataType = !empty($this->fields[$key]['customField']['data_type']) ? $this->fields[$key]['customField']['data_type'] : null;

    if (is_array($value) && $dataType !== 'File') {
      $valueArray = array();
      foreach ($value as $valueKey => $valueItem) {
        $valueArray[] = $this->formatValue($key, $valueKey, $level + 1);
      }
      return $valueArray;
    }

    if (!empty($this->formattedValues[$key][$value])) {
      return $this->formattedValues[$key][$value];
    }

    if (!empty($this->fields[$key]['customField'])) {
      switch ($this->fields[$key]['customField']['data_type']) {
        case 'File':
          $result = CRM_Utils_System::formatWikiURL($value['fileURL'] . ' ' . $value['fileName']);
          $this->formattedValues[$key][$value] = $result;
          return $result;
        break;
        // For few field types we can use 'formatCustomValues()' core method.
        case 'Date':
        case 'Boolean':
        case 'Link':
        case 'StateProvince':
        case 'Country':
          $data = array('data' => $value);
          CRM_Utils_System::url();
          $result = CRM_Core_BAO_CustomGroup::formatCustomValues($data, $this->fields[$key]['customField']);
          $this->formattedValues[$key][$value] = $result;
          return $result;
        break;
        // Anyway, 'formatCustomValues()' core method doesn't handle some types
        // such as 'CheckBox' (looks like they aren't implemented there) so
        // we deal with them automatically by custom handling of 'optionValues' array.
      }
    }

    if (!empty($this->fields[$key]['optionValues'])) {
      $result = $this->fields[$key]['optionValues'][$value];
      $this->formattedValues[$key][$value] = $result;
      return $result;
    }

    $result = strip_tags($this->customizeValue($key, $value));
    $this->formattedValues[$key][$value] = $result;

    return $result;
  }

  /**
   * Additional function for customizing Entity value by its key
   * (if it's needed). For example: we want to return Campaign's title
   * instead of ID.
   *
   * @param string $key
   *   Field key
   * @param string $value
   *   Field value
   *
   * @return string
   */
  protected function customizeValue($key, $value) {
    if (!empty($this->customizedValues[$key][$value])) {
      return $this->customizedValues[$key][$value];
    }

    $result = $value;

    $this->customizedValues[$key][$value] = $result;

    return $result;
  }

  /**
   * Returns an empty row containing field names as keys.
   *
   * @return array
   */
  protected function getEmptyRow() {
    $result = array();

    foreach ($this->fields as $key => $value) {
      if (!empty($value['title'])) {
        $key = $value['title'];
      }
      $result[$key] = '';
    }

    return $result;
  }

  /**
   * Returns an array containing all Fields and Custom Fields of entity,
   * keyed by their API keys and extended with available fields Option Values.
   *
   * @return array
   */
  abstract protected function getFields();

  /**
   * Gets total number of entities.
   *
   * @param array $params
   *
   * @return int
   */
  abstract protected function getCount(array $params);
}
