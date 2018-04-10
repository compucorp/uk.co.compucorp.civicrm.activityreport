<?php

use CRM_PivotCache_AbstractGroup as AbstractGroup;

/**
 * @inheritdoc
 */
abstract class CRM_PivotData_AbstractData implements CRM_PivotData_DataInterface {

  /**
   * Limit value for API 'get' action on source entity. Used on rebuilding
   * Pivot Data.
   */
  const ROWS_API_LIMIT = 1000;

  /**
   * Maximum number of data rows per page (single cache row).
   */
  const ROWS_PAGINATED_LIMIT = 10000;

  /**
   * Maximum number of Multi Values generated by splitMultiValues() method at once.
   */
  const ROWS_MULTIVALUES_LIMIT = 1000;

  /**
   * A number of Pivot Report rows to get from cache with a single 'get' action
   * on Pivot Data.
   */
  const ROWS_RETURN_LIMIT = 10000;

  /**
   * Entity fields.
   *
   * @var array
   */
  protected $fields = array();

  /**
   * Empty Pivot Report row containing Entity fields as keys and NULL values.
   *
   * @var array
   */
  protected $emptyRow = array();

  /**
   * Additional fields we want to attach to each Pivot row.
   * Useful when we want to populate them on front-end app with Pivot Table
   * library's derived attributes.
   *
   * @var array
   */
  protected $additionalHeaderFields = array();

  /**
   * An array containing Multi Values for particular Entity row.
   *
   * @var array
   */
  protected $multiValues = array();

  /**
   * An array containing output values basing on original Entity values.
   *
   * @var array
   */
  protected $formattedValues = array();

  /**
   * An array containing customized values basing on original Entity values.
   *
   * @var array
   */
  protected $customizedValues = array();

  /**
   * Name of data group.
   *
   * @var string 
   */
  protected $name = NULL;

  /**
   * CRM_PivotData_AbstractData constructor.
   *
   * Some entities may have different API name than data group name. In this case
   *
   * @param string $name
   *  Report Entity Name
   */
  public function __construct($name) {
    $this->name = $name;

    $dateFields = $this->getDateFields();

    foreach ($dateFields as $field) {
      $this->additionalHeaderFields[$field . ' (' . ts('per month') . ')'] = '';
    }
  }

  /**
   * Returns instance of data class for given entity.
   *
   * @param string $entity
   *   Name of entity
   *
   * @return \CRM_PivotData_AbstractData
   */
  public static function getInstance($entity) {

    $className = 'CRM_PivotData_Data' . $entity;
    $dataInstance = new $className();

    return $dataInstance;
  }

  /**
   * @inheritdoc
   */
  public function get(AbstractGroup $cacheGroup, array $params, $page = 0) {
    $dataSetInstance = new CRM_PivotCache_DataSet($this->name);
    $dataSet = $dataSetInstance->get($cacheGroup, $page, $this::ROWS_RETURN_LIMIT, $params);

    return array(
      array(
      'nextKeyValue' => $dataSet->getNextIndex(),
      'nextPage' => $dataSet->getNextPage(),
      'data' => $dataSet->getData(),
    ));
  }

  /**
   * Returns fields and values basing on specified entity name.
   *
   * @param array $data
   * @param string $entityName
   *
   * @return array
   */
  protected function getRowValues($data, $entityName) {
    $result = array();
    $fields = $this->getFields();

    foreach ($data as $key => $value) {
      $fieldsKey = $entityName . '.' . $key;

      if (empty($fields[$fieldsKey])) {
        continue;
      }

      $resultKey = $fieldsKey;
      if (!is_array($fields[$fieldsKey])) {
        $resultKey = $fields[$fieldsKey];
      }

      if(isset($fields[$resultKey]['handler'])) {
        if(method_exists($this, $fields[$resultKey]['handler'])) {
          $value = call_user_func([$this, $fields[$resultKey]['handler']], $value, $data);
        }
      }

      $result[$resultKey] = $value;
    }

    return $result;
  }

  /**
   * Returns an array containing formatted rows of specified array.
   *
   * @param int $baseKey
   * @param array $row
   *
   * @return array
   */
  protected function formatRow($baseKey, $row) {
    $fields = $this->getFields();
    $result = array();

    foreach ($row as $key => $value) {
      $label = $key;
      if (!empty($fields[$key]['title'])) {
        $label = $fields[$key]['title'];
      }
      $label = ts($label);

      $formattedValue = $this->formatValue($key, $value);
      $result[$label] = $formattedValue;

      if (is_array($formattedValue)) {
        $this->multiValues[$baseKey][] = $label;
      }
    }

    ksort($result);

    return $result;
  }

  /**
   * @inheritdoc
   */
  public function rebuildCache(AbstractGroup $cacheGroup, array $params) {
    $this->emptyRow = $this->getEmptyRow();
    $this->multiValues = array();

    $time = microtime(true);

    $cacheGroup->clear();

    $totalCount = $this->getCount($params);
    $this->rebuildEntityCount($cacheGroup, $totalCount);

    $result = $this->rebuildData($cacheGroup, $params);

    $this->rebuildHeader($cacheGroup, array_merge($this->emptyRow, $this->additionalHeaderFields));
    $this->rebuildPivotCount($cacheGroup, $result['count']);

    CRM_PivotReport_BAO_PivotReportCache::deleteActiveCache($cacheGroup->getName());
    CRM_PivotReport_BAO_PivotReportCache::activateCache($cacheGroup);

    return array(
      array(
        'rows' => $result['count'],
        'time' => (microtime(true) - $time),
      )
    );
  }

  /**
   * @inheritdoc
   */
  public function rebuildCachePartial(AbstractGroup $cacheGroup, array $params, $offset, $multiValuesOffset, $index, $page, $pivotCount) {
    $this->emptyRow = $this->getEmptyRow();
    $this->multiValues = array();

    if (!$offset && !$multiValuesOffset) {
      $cacheGroup->clear();

      $totalCount = $this->getCount($params);
      $this->rebuildEntityCount($cacheGroup, $totalCount);
    }

    $result = $this->rebuildData($cacheGroup, $params, $offset, $offset + $this::ROWS_API_LIMIT, $multiValuesOffset, $index, $page, TRUE);

    if (!$result['count']) {
      $this->rebuildHeader($cacheGroup, array_merge($this->emptyRow, $this->additionalHeaderFields));
      $this->rebuildPivotCount($cacheGroup, $pivotCount);

      CRM_PivotReport_BAO_PivotReportCache::deleteActiveCache($cacheGroup->getName());
      CRM_PivotReport_BAO_PivotReportCache::activateCache($cacheGroup);
    }

    return $result;
  }

  /**
   * @inheritdoc
   */
  public function rebuildData(AbstractGroup $cacheGroup, array $params, $offset = 0, $total = NULL, $multiValuesOffset = 0, $index = NULL, $page = 0, $isPartial = FALSE) {
    $totalCount = $this->getCount($params);

    if ($total === NULL) {
      $total = $totalCount;
    } else {
      if ($total > $totalCount) {
        $total = $totalCount;
      }
    }

    $count = 0;

    while ($offset < $total) {
      $pages = $this->getPaginatedResults($params, $offset, $multiValuesOffset, $page, $index);
      $count += $this->cachePages($cacheGroup, $pages);

      $lastPageIndex = count($pages) - 1;
      $offset = $pages[$lastPageIndex]->getNextOffset();
      $multiValuesOffset = $pages[$lastPageIndex]->getNextMultiValuesOffset();
      $page = $pages[$lastPageIndex]->getPage() + 1;
      $index = $pages[$lastPageIndex]->getIndex();

      if ($isPartial) {
        break;
      }
    }

    return array(
      'offset' => $offset,
      'multiValuesOffset' => $multiValuesOffset,
      'page' => $page,
      'index' => $index,
      'count' => $count,
    );
  }

  /**
   * @inheritdoc
   */
  public function rebuildHeader(AbstractGroup $cacheGroup, array $header) {
    $cacheGroup->cacheHeader($header);
  }

  /**
   * Rebuilds entity count cache value.
   *
   * @param \CRM_PivotCache_AbstractGroup $cacheGroup
   * @param int $entityCount
   */
  public function rebuildEntityCount(AbstractGroup $cacheGroup, $entityCount) {
    $cacheGroup->setCacheValue('entityCount', $entityCount);
  }

  /**
   * Returns entity count cache value.
   *
   * @param \CRM_PivotCache_AbstractGroup $cacheGroup
   */
  public function getEntityCount(AbstractGroup $cacheGroup) {
    return $cacheGroup->getCacheValue('entityCount');
  }

  /**
   * Rebuilds pivot count cache value.
   *
   * @param \CRM_PivotCache_AbstractGroup $cacheGroup
   * @param int $pivotCount
   */
  public function rebuildPivotCount(AbstractGroup $cacheGroup, $pivotCount) {
    $cacheGroup->setCacheValue('pivotCount', $pivotCount);
  }

  /**
   * Returns pivot count cache value.
   *
   * @param \CRM_PivotCache_AbstractGroup $cacheGroup
   */
  public function getPivotCount(AbstractGroup $cacheGroup) {
    return $cacheGroup->getCacheValue('pivotCount');
  }

  /**
   * Returns an array of entity data pages.
   *
   * @param array $inputParams
   * @param int $offset
   * @param int $multiValuesOffset
   * @param int $page
   * @param string $index
   *
   * @return int
   */
  protected function getPaginatedResults($inputParams, $offset = 0, $multiValuesOffset = 0, $page = 0, $index = NULL) {
    $result = array();
    $rowsCount = 0;
    $entities = $this->getData($inputParams, $offset);
    $formattedEntities = $this->formatResult($entities);

    unset($entities);

    while (!empty($formattedEntities)) {
      $split = $this->splitMultiValues($formattedEntities, $offset, $multiValuesOffset);
      $rowsCount += count($split['data']);

      if ($rowsCount > $this::ROWS_PAGINATED_LIMIT) {
        break;
      }

      if ($split['info']['index'] !== $index) {
        $page = 0;
        $index = $split['info']['index'];
      }

      $result[] = new CRM_PivotData_DataPage($split['data'], $index, $page++, $split['info']['nextOffset'], $split['info']['multiValuesOffset']);

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
   * @param \CRM_PivotCache_AbstractGroup $cacheGroup
   * @param array $pages
   *
   * @return int
   */
  protected function cachePages(AbstractGroup $cacheGroup, array $pages) {
    $count = 0;

    foreach ($pages as $page) {
      $count += $cacheGroup->cachePage($page);
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
      'return' => implode(',', array_keys($this->getFields())),
      'options' => array(
        'limit' => $this::ROWS_API_LIMIT,
      ),
    );

    return $params;
  }

  /**
   * Returns an array containing a matrix of multiple values for particular
   * $data row.
   * For example, if a $data row contains one or more fields with multi values
   * then the result is an array containing all possible combinations of
   * these multi option values.
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
      $entityIndexValue = $this->getEntityIndex($row);

      if (!$index) {
        $index = $entityIndexValue;
      }

      if ($index !== $entityIndexValue) {
        break;
      }

      $multiValuesRows = null;
      if (!empty($this->multiValues[$key])) {
        $multiValuesFields = array_combine($this->multiValues[$key], array_fill(0, count($this->multiValues[$key]), 0));

        $multiValuesRows = $this->populateMultiValuesRow($row, $multiValuesFields, $multiValuesOffset, $this::ROWS_MULTIVALUES_LIMIT - $i);

        $result = array_merge($result, $multiValuesRows['data']);
        $multiValuesOffset = 0;
      } else {
        $result[] = array_values($row);
      }
      $i = count($result);

      $totalOffset++;

      if ($i >= $this::ROWS_MULTIVALUES_LIMIT) {
        break;
      }

      unset($this->multiValues[$key]);
    }

    return array(
      'info' => array(
        'index' => $index,
        'nextOffset' => !empty($multiValuesRows['info']['multiValuesOffset']) ? $totalOffset - 1: $totalOffset,
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
    $fields = $this->getFields();

    if ($level < 2) {
      if ($level === 1) {
        $result = $this->emptyRow;
      }
      $baseKey = $dataKey;
      foreach ($data as $key => $value) {
        if (empty($fields[$key]) && $level) {
          continue;
        }
        $dataKey = $key;

        if (!empty($fields[$key]['title'])) {
          $key = $fields[$key]['title'];
        }

        $result[$key] = $this->formatResult($value, $dataKey, $level + 1);

        if ($level === 1 && is_array($result[$key])) {
          $this->multiValues[$baseKey][] = $key;
        }
      }

      if ($level === 1) {
        if (!empty($this->additionalHeaderFields)) {
          $result = array_merge($result, $this->additionalHeaderFields);
        }

        ksort($result);
      }
    } else {
      return $this->formatValue($dataKey, $data);
    }

    return $result;
  }

  /**
   * Returns $value formatted by available Option Values for the $key Field.
   * If there are no Option Values for the field, then return $value itself
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
    if (trim($value) == '' || $level > 1) {
      return '';
    }

    $fields = $this->getFields();
    $coreType = !empty($fields[$key]['type']) ? $fields[$key]['type'] : null;
    $dataType = !empty($fields[$key]['customField']) ? $fields[$key]['customField']['data_type'] : null;
    $customHTMLType = !empty($fields[$key]['customField']) ? $fields[$key]['customField']['html_type'] : null;

    // Handle multiple values
    if (is_array($value) && $dataType !== 'File') {
      $valueArray = array();
      foreach ($value as $valueKey => $valueItem) {
        $valueArray[] = $this->formatValue($key, $valueItem, $level + 1);
      }

      return $valueArray;
    }

    if (!is_array($value) && !empty($this->formattedValues[$key][$value])) {
      return $this->formattedValues[$key][$value];
    }

    switch (true) {
      // Anyway, 'formatCustomValues()' core method doesn't handle some types
      // such as 'CheckBox' (looks like they aren't implemented there) so
      // we deal with them automatically by custom handling of 'optionValues' array.
      case !empty($fields[$key]['optionValues']):
        if (isset($fields[$key]['optionValues'][$value])) {
          $result = $fields[$key]['optionValues'][$value];
        } else {
          $result = '';
        }
        break;

      // Protect string values from line-breaks
      case $coreType & CRM_Utils_Type::T_LONGTEXT:
      case $coreType & CRM_Utils_Type::T_TEXT:
      case $coreType & CRM_Utils_Type::T_STRING:
      case $dataType == 'String':
      case $dataType == 'Memo':
      case $customHTMLType == 'Text':
      case $customHTMLType == 'TextArea':
        $result = strtr($value, array("\r\n" => ' ', "\n" => ' ', "\r" => ' '));
        break;

      // Handle files
      case $dataType == 'File':
        if (is_array($value)) {
          if (isset($value['fileURL']) && isset($value['fileName'])) {
            $result = CRM_Utils_System::formatWikiURL($value['fileURL'] . ' ' . $value['fileName']);
          } else {
            $result = CRM_Utils_System::formatWikiURL(implode(' ', $value));
          }
        } else {
          $result = $value;
        }
        break;

      // For few field types we can use 'displayValue()' core method.
      case $dataType == 'Date':
      case $dataType == 'Boolean':
      case $dataType == 'Link':
      case $dataType == 'StateProvince':
      case $dataType == 'Country':
        $data = array('data' => $value);
        CRM_Utils_System::url();
        $result = CRM_Core_BAO_CustomField::displayValue($data, $fields[$key]['customField']);
        break;

      default:
        $result = strip_tags($this->customizeValue($key, $value));
        break;
    }

    if (!is_array($value)) {
      $this->formattedValues[$key][$value] = $result;
    }

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
    $customValue = $this->getCustomValue($key, $value);

    if (!$customValue) {
      $this->setCustomValue($key, $value);
    }

    return $this->getCustomValue($key, $value);
  }

  /**
   * Returns customized value for specified key and value.
   *
   * @param string $key
   * @param string $value
   *
   * @return mixed|NULL
   */
  protected function getCustomValue($key, $value) {
    return !empty($this->customizedValues[$key][$value]) ? $this->customizedValues[$key][$value] : NULL;
  }

  /**
   * Sets customized value by specified key and value.
   *
   * @param string $key
   * @param string $value
   */
  protected function setCustomValue($key, $value) {
    $this->customizedValues[$key][$value] = $value;
  }

  /**
   * Returns an empty row containing field names as keys.
   *
   * @return array
   */
  protected function getEmptyRow() {
    $result = array();
    $fields = $this->getFields();

    foreach ($fields as $key => $value) {
      if (!is_array($value) && !empty($value)) {
        $key = $value;
      } elseif (!empty($value['title'])) {
        $key = $value['title'];
      }

      $result[$key] = '';
    }

    ksort($result);
    return $result;
  }

  /**
   * @inheritdoc
   */
  public function getDateFields() {
    $result = array();
    $fields = $this->getFields();

    foreach ($fields as $field => $fieldData) {
      if (!empty($fieldData['type']) && ($fieldData['type'] & CRM_Utils_Type::T_DATE)) {
        $result[] = $fieldData['title'];
      }
    }

    return $result;
  }

  /**
   * Returns available Option Values of specified $field array within specified
   * $entity.
   * If there is no available Option Values for the field, then return null.
   *
   * @param array $field
   *   Field key
   * @param string $apiEntity
   *
   * @return array
   */
  protected function getOptionValues($field, $apiEntity) {
    if (empty($field['pseudoconstant']['optionGroupName']) && empty($field['pseudoconstant']['table'])) {
      return NULL;
    }

    $result = civicrm_api3($apiEntity, 'getoptions', array(
      'field' => $field['name'],
    ));

    return $result['values'];
  }

  /**
   * Returns name property value.
   *
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Returns a string containing Entity index basing on Entity row.
   *
   * The Entity Index is used as a part of cache key. It may be an Entity ID, date,
   * some kind of category or any other value which we want to use as cache key.
   *
   * Example of cache key:
   *
   * data_2017-08-30_000001
   *
   * Particular parts of cache key are separated by '_' character so the
   * Entity Index can't contain it.
   *
   * 'data' is a constant string as a prefix for cache key of Pivot data.
   * '2017-08-30' is the Entity Index (so it's date value).
   * '000001' is page value which is irrelevant in terms of this method.
   * It basically means that the row contains page '1' of '2017-08-30' index.
   *
   * Picking the correct value of Entity Index can be determined by a field that
   * we want to use as primary filter value when searching on pivotreport cache
   * group.
   *
   * @param array $row
   *
   * @return string
   */
  abstract protected function getEntityIndex(array $row);

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
  abstract public function getCount(array $params = array());

  /**
   * Returns the data to be cached for the Report.
   * The data can be from API or Sql queries.
   *
   * @param array $inputParams
   *  Additional parameters needed to get the Data.
   * @param int $offset
   *
   * @return array
   */
  abstract protected function getData(array $inputParams, $offset);

  /**
   * Returns an array containing API date filter conditions basing on specified
   * dates.
   *
   * @param string $startDate
   * @param string $endDate
   *
   * @return array|NULL
   */
  protected function getAPIDateFilter($startDate, $endDate) {
    $apiFilter = null;

    if (!empty($startDate) && !empty($endDate)) {
      $apiFilter = ['BETWEEN' => [$startDate, $endDate]];
    }
    else if (!empty($startDate) && empty($endDate)) {
      $apiFilter = ['>=' => $startDate];
    }
    else if (empty($startDate) && !empty($endDate)) {
      $apiFilter = ['<=' => $endDate];
    }

    return $apiFilter;
  }
}
