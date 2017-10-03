CRM.PivotReport = CRM.PivotReport || {};

CRM.PivotReport.PivotTable = (function($) {

  /**
   * Initializes Pivot Table.
   *
   * @param {object} config
   */
  function PivotTable(config) {
    var defaults = {
      'entityName': null,
      'filter': false,
      'initialLoad': {
        'limit': 0,
        'message': '',
        'getFilter': function() {
          return new CRM.PivotReport.Filter(null, null);
        }
      },
      'getCountParams': function(keyValueFrom, keyValueTo) {
        return {};
      },
      'initFilterForm': function(keyValueFromField, keyValueToField) {},
      'derivedAttributes': {},
      'hiddenAttributes': []
    };

    this.config = $.extend(true, {}, defaults, config);

    this.header = [];
    this.data = [];
    this.total = 0;
    this.pivotReportForm = null;
    this.pivotReportKeyValueFrom = null;
    this.pivotReportKeyValueTo = null;
    this.dateFields = null;
    this.relativeFilters = null;
    this.crmConfig = null;

    this.initFilterForm();
    this.initUI();
    this.initPivotDataLoading();
  };

  /**
   * Initializes date filters for each field of Date data type.
   */
  PivotTable.prototype.initDateFilters = function () {
    var that = this;

    $('div.pvtFilterBox').each(function () {
      var container = $(this);
      var fieldName = '';

      $(this).children().each(function () {

        if ($(this).prop("tagName") == 'H4') {
          fieldName = $(this).text().replace(/[ ()0-9]/g, '');

          if ($.inArray($(this).text().replace(/[()0-9]/g, ''), that.dateFields) >= 0) {
            $(this).after('' +
              '<div class="inner_date_filters">' +
              ' <form>' +
              '   <input type="text" id="fld_' + fieldName + '_start" name="fld_' + fieldName + '_start" class="inner_date fld_' + fieldName + '_start" value=""> - ' +
              '   <input type="text" id="fld_' + fieldName + '_end" name="fld_' + fieldName + '_end" class="inner_date fld_' + fieldName + '_end" value="">' +
              ' </form>' +
              '</div>'
            );

            var selectContainer = $('<p>');
            var relativeSelect = $('<select>');
            relativeSelect.attr('name', 'sel_' + fieldName);
            relativeSelect.addClass('relativeFilter');
            relativeSelect.change(function () {
              that.changeFilterDates($(this));
            });

            relativeSelect.append($("<option>").attr('value', '').text('- Any -'));
            $(that.relativeFilters).each(function () {
              relativeSelect.append($("<option>").attr('value', this.value).text(this.label));
            });

            selectContainer.append(relativeSelect);
            $(this).after(selectContainer);

            $('.pvtFilter', container).addClass(fieldName);
          }
        }
      });

      $(':button', container).each(function () {
        if ($(this).text() == 'Select All') {
          $(this).addClass(fieldName + '_batchSelector');
          $(this).off('click');
          $(this).on('click', function () {
            $('#fld_' + fieldName + '_start').change();
          });
        }
      });
    });

    $('.inner_date').each(function () {

      $(this).change(function () {
        var fieldInfo = $(this).attr('name').split('_');

        var startDateValue = $('#fld_' + fieldInfo[1] + '_start').val();
        var startDate = new Date(startDateValue);
        var startTime = startDate.getTime();

        var endDateValue = $('#fld_' + fieldInfo[1] + '_end').val();
        var endDate = new Date(endDateValue);
        var endTime = endDate.getTime();

        $('input.' + fieldInfo[1]).each(function () {
          var checkDate = new Date($('span.value', $(this).parent()).text());
          var timeCheck = checkDate.getTime();
          var checked = false;

          if (startDateValue != '' && endDateValue != '') {
            if (timeCheck >= startTime && timeCheck <= endTime) {
              checked = true;
            }
          } else if (startDateValue != '') {
            if (timeCheck >= startTime) {
              checked = true;
            }
          } else if (endDateValue != '') {
            if (timeCheck <= endTime) {
              checked = true;
            }
          } else {
            checked = true;
          }

          if (checked == true && !$(this).is(':checked')) {
            $(this).click();
          } else if (checked == false && $(this).is(':checked')) {
            $(this).click();
          }

        });
      });

      $(this).crmDatepicker({
        time: false
      });
    });

  }

  PivotTable.prototype.changeFilterDates = function (select) {
    var relativeDateInfo = select.val().split('.');
    var unit = relativeDateInfo[1];
    var relativeTerm = relativeDateInfo[0];
    var dates = {};

    switch (unit) {
      case 'year':
        dates = this.calculateRelativeYearDates(relativeTerm);
        break;

      case 'fiscal_year':
        dates = this.calculateRelativeFiscalYearDates(relativeTerm);
        break;
      case 'quarter':
        dates = this.calculateRelativeQuarterDates(relativeTerm);
        break;
      case 'month':
        dates = this.calculateRelativeMonthDates(relativeTerm);
        break;
      case 'week':
        dates = this.calculateRelativeWeekDates(relativeTerm);
        break;
      case 'day':
        dates = this.calculateRelativeDayDates(relativeTerm);
        break;
    }

    var fieldInfo = select.attr('name').split('_');
    var fieldName = fieldInfo[1];

    $('#fld_' + fieldName + '_start').val(CRM.utils.formatDate(dates.startDate, CRM.config.dateInputFormat)).change();
    $('input.inner_date.fld_' + fieldName + '_start.hasDatepicker').val(CRM.utils.formatDate(dates.startDate, CRM.config.dateInputFormat));

    $('#fld_' + fieldName + '_end').val(CRM.utils.formatDate(dates.endDate, CRM.config.dateInputFormat)).change();
    $('input.inner_date.fld_' + fieldName + '_end.hasDatepicker').val(CRM.utils.formatDate(dates.endDate, CRM.config.dateInputFormat));
  }

  /**
   * Calculates start and end dates for given day-relative interval.
   *
   * @param relativeTerm
   *   Relative interval to calculate start and end dates.
   *
   * @returns {{startDate: Date, endDate: Date}}
   *   Object with calculated start and end dates.
   */
  PivotTable.prototype.calculateRelativeDayDates = function (relativeTerm) {
    var today = new Date();
    var startDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    var endDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    switch (relativeTerm) {

      case 'this':
        startDate.startOf('day');
        endDate.endOf('day');
        break;

      case 'previous':
        startDate.subtract(1, 'day');
        endDate.subtract(1, 'day');

        startDate.startOf('day');
        endDate.endOf('day');
        break;

      case 'starting':
        startDate.add(1, 'day');
        endDate.add(1, 'day');

        startDate.startOf('day');
        endDate.endOf('day');
        break;
    }

    return {
      'startDate': startDate.toDate(),
      'endDate': endDate.toDate()
    };
  }


  /**
   * Calculates start and end dates for given week-relative interval.
   *
   * @param relativeTerm
   *   Relative interval to calculate start and end dates.
   *
   * @returns {{startDate: Date, endDate: Date}}
   *   Object with calculated start and end dates.
   */
  PivotTable.prototype.calculateRelativeWeekDates = function (relativeTerm) {
    var today = new Date();
    var startDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    var endDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    switch (relativeTerm) {

      case 'this':
        startDate.startOf('week');
        endDate.endOf('week');
        break;

      case 'previous':
        startDate.subtract(1, 'week');
        endDate.subtract(1, 'week');

        startDate.startOf('week');
        endDate.endOf('week');
        break;

      case 'ending':
        startDate.subtract(7, 'days');
        break;

      case 'next':
        startDate.add(1, 'week');
        endDate.add(1, 'week');

        startDate.startOf('week');
        endDate.endOf('week');
        break;
    }

    return {
      'startDate': startDate.toDate(),
      'endDate': endDate.toDate()
    };
  }

  /**
   * Calculates start and end dates for given month-relative interval.
   *
   * @param relativeTerm
   *   Relative interval to calculate start and end dates.
   *
   * @returns {{startDate: Date, endDate: Date}}
   *   Object with calculated start and end dates.
   */
  PivotTable.prototype.calculateRelativeMonthDates = function (relativeTerm) {
    var today = new Date();
    var startDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    var endDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    switch (relativeTerm) {

      case 'this':
        startDate.startOf('month');
        endDate.endOf('month');
        break;

      case 'previous':
        startDate.subtract(1, 'month');
        endDate.month(startDate.month());

        startDate.startOf('month');
        endDate.endOf('month');
        break;

      case 'ending':
        startDate.subtract(30, 'days');
        break;

      case 'ending_2':
        startDate.subtract(60, 'days');
        break;

      case 'next':
        startDate.add(1, 'month');
        endDate.month(startDate.month());

        startDate.startOf('month');
        endDate.endOf('month');
        break;
    }

    return {
      'startDate': startDate.toDate(),
      'endDate': endDate.toDate()
    };
  }

  /**
   * Calculates start and end dates for given year-relative interval.
   *
   * @param relativeTerm
   *   Relative interval to calculate start and end dates.
   *
   * @returns {{startDate: Date, endDate: Date}}
   *   Object with calculated start and end dates.
   */
  PivotTable.prototype.calculateRelativeYearDates = function (relativeTerm) {
    var today = new Date();
    var startDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    var endDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    switch (relativeTerm) {

      case 'this':
        startDate.startOf('year');
        endDate.endOf('year');
        break;

      case 'previous':
        startDate.subtract(1, 'years');
        startDate.startOf('year');

        endDate.subtract(1, 'years');
        endDate.endOf('year');
        break;

      case 'ending':
        startDate.subtract(1, 'years');
        break;

      case 'ending_2':
        startDate.subtract(2, 'years');
        break;

      case 'ending_3':
        startDate.subtract(3, 'years');
        break;

      case 'next':
        startDate.add(1, 'years');
        startDate.startOf('year');

        endDate.add(1, 'years');
        endDate.endOf('year');
        break;
    }

    return {
      'startDate': startDate.toDate(),
      'endDate': endDate.toDate()
    };
  }

  /**
   * Calculates start and end dates of quarter-relative interval.
   *
   * @param relativeTerm
   *   Relative interval used to calculate start and end dates.
   *
   * @returns {{startDate: Date, endDate: Date}}
   *   Object with calculated start and end dates.
   */
  PivotTable.prototype.calculateRelativeQuarterDates = function (relativeTerm) {
    var today = new Date();
    var startDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    var endDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    switch (relativeTerm) {

      case 'this':
        startDate.startOf('quarter');
        endDate.endOf('quarter');
        break;

      case 'previous':
        startDate.subtract(1, 'quarters');
        startDate.startOf('quarter');

        endDate.subtract(1, 'quarters');
        endDate.endOf('quarter');
        break;

      case 'ending':
        startDate.subtract(90, 'days');
        break;

      case 'next':
        startDate.add(1, 'quarters');
        startDate.startOf('quarter');

        endDate.add(1, 'quarters');
        endDate.endOf('quarter');
        break;
    }

    return {
      'startDate': startDate.toDate(),
      'endDate': endDate.toDate()
    };
  }

  /**
   * Calculates start and end dates for given fiscal year-relative interval.
   *
   * @param relativeTerm
   *   Relative interval to calculate start and end dates.
   *
   * @returns {{startDate: Date, endDate: Date}}
   *   Object with calculated start and end dates.
   */
  PivotTable.prototype.calculateRelativeFiscalYearDates = function (relativeTerm) {

    var today = new Date();
    var startDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    var endDate = new moment({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate()
    });

    var fiscalBeginningDay = parseInt(this.crmConfig.fiscalYearStart.d);
    var fiscalBeginningMonth = parseInt(this.crmConfig.fiscalYearStart.M) - 1;

    if (relativeTerm == 'previous') {
      startDate.subtract(1, 'years');
    } else if (relativeTerm == 'next') {
      startDate.add(1, 'years');
    }

    if (startDate.month() < fiscalBeginningMonth) {
      startDate.subtract(1, 'years');
    }

    startDate.month(fiscalBeginningMonth);
    startDate.date(fiscalBeginningDay);

    endDate.year(startDate.year());
    endDate.month(fiscalBeginningMonth);
    endDate.date(fiscalBeginningDay);
    endDate.add(1, 'years');
    endDate.subtract(1, 'ms');

    return {
      'startDate': startDate.toDate(),
      'endDate': endDate.toDate()
    };
  }

  /**
   * Initializes Pivot Report filter form.
   */
  PivotTable.prototype.initFilterForm = function() {
    if (!this.config.filter) {
      return;
    }

    var that = this;

    this.pivotReportForm = $('#pivot-report-filters form');
    this.pivotReportKeyValueFrom = $('input[name="keyvalue_from"]', this.pivotReportForm);
    this.pivotReportKeyValueTo = $('input[name="keyvalue_to"]', this.pivotReportForm);

    $('input[type="button"].apply-filters-button', this.pivotReportForm).click(function(e) {
      $('#pivot-report-preloader').removeClass('hidden');
      $('#pivot-report-filters').addClass('hidden');

      that.loadDataByFilter(that.pivotReportKeyValueFrom.val(), that.pivotReportKeyValueTo.val());
    });

    $('input[type="button"].load-all-data-button', this.pivotReportForm).click(function(e) {
      CRM.confirm({ message: 'This operation may take some time to load all data for big data sets. Do you really want to load all Activities data?' }).on('crmConfirm:yes', function() {
        that.loadAllData();
      });
    });

    this.config.initFilterForm(this.pivotReportKeyValueFrom, this.pivotReportKeyValueTo);
  };

  /**
   * Handles UI events.
   */
  PivotTable.prototype.initUI = function() {
    var that = this;

    $('input[type="button"].build-cache-button').click(function(e) {
      CRM.confirm({message: 'This operation may take some time to build the cache. Do you really want to build the cache for ' + that.config.entityName + ' data?' })
      .on('crmConfirm:yes', function() {
        CRM.api3('ActivityReport', 'rebuildcache', {entity: that.config.entityName}).done(function(result) {
          that.initPivotDataLoading();
        });
      });
    });
  }

  /**
   * Loads header, checks total number of items and then starts data fetching.
   */
  PivotTable.prototype.initPivotDataLoading = function() {
    var that = this;
    var apiCalls = {
      'getConfig': ['Setting', 'get', {
        'sequential': 1,
        'return': ['weekBegins', 'fiscalYearStart']
      }],
      'getHeader': ['ActivityReport', 'getheader', {'entity': this.config.entityName}],
      'getCount': [this.config.entityName, 'getcount', that.config.getCountParams()],
      'dateFields': ['ActivityReport', 'getdatefields', {entity: this.config.entityName}],
      'relativeFilters': ['OptionValue', 'get', {
        'sequential': 1,
        'option_group_id': 'relative_date_filters'
      }],
    };

    CRM.api3(apiCalls).done(function (result) {
      that.dateFields = result.dateFields.values;
      that.relativeFilters = result.relativeFilters.values
      that.header = result.getHeader.values;
      that.total = parseInt(result.getCount.result, 10);
      that.crmConfig = result.getConfig.values[0];

      if (that.config.initialLoad.limit && that.total > that.config.initialLoad.limit) {
        CRM.alert(that.config.initialLoad.message, '', 'info');

        $('input[type="button"].load-all-data-button', this.pivotReportForm).removeClass('hidden');
        var filter = that.config.initialLoad.getFilter();

        that.loadDataByFilter(filter.getFrom(), filter.getTo());
      } else {
        that.loadAllData();
      }
    });
  };

  /**
   * Resets data array and init empty Pivot Table.
   */
  PivotTable.prototype.resetData = function() {
    this.data = [];
    this.initPivotTable([]);
  };

  /**
   * Loads a pack of Pivot Report data. If there is more data to load
   * (depending on the total value and the response) then we run
   * the function recursively.
   *
   * @param {object} loadParams
   *   Object containing params for API 'get' request of Pivot Report data.
   */
  PivotTable.prototype.loadData = function(loadParams) {
    var that = this;

    CRM.$('span#pivot-report-loading-count').append('.');

    var params = loadParams;
    params.sequential = 1;
    params.entity = this.config.entityName;

    CRM.api3('ActivityReport', 'get', params).done(function(result) {
      that.data = that.data.concat(that.processData(result['values'][0].data));
      var nextKeyValue = result['values'][0].nextKeyValue;
      var nextPage = result['values'][0].nextPage;

      if (nextKeyValue === '') {
        that.loadComplete(that.data);
      } else {
        that.loadData({
          "keyvalue_from": nextKeyValue,
          "keyvalue_to": params.keyvalue_to,
          "page": nextPage
        });
      }
    });
  };

  /**
   * Hides preloader, show filters and init Pivot Table.
   *
   * @param {array} data
   */
  PivotTable.prototype.loadComplete = function(data) {
    $('#pivot-report-preloader').addClass('hidden');

    if (this.config.filter) {
      $('#pivot-report-filters').removeClass('hidden');
    }

    this.initPivotTable(data);
    this.data = [];
  };

  /**
   * Formats incoming data (combine header with fields values)
   * to be compatible with Pivot library.
   *
   * @param {array} data
   *
   * @returns {array}
   */
  PivotTable.prototype.processData = function(data) {
    var that = this;
    var result = [];
    var i, j;

    for (i in data) {
      var row = {};
      for (j in data[i]) {
        row[that.header[j]] = data[i][j];
      }
      result.push(row);
    }

    return result;
  };

  /**
   * Runs data loading by specified filter values.
   *
   * @param {string} filterValueFrom
   * @param {string} filterValueTo
   */
  PivotTable.prototype.loadDataByFilter = function(filterValueFrom, filterValueTo) {
    var that = this;

    this.resetData();

    if (this.config.filter) {
      this.pivotReportKeyValueFrom.val(filterValueFrom).trigger('change');
      this.pivotReportKeyValueTo.val(filterValueTo).trigger('change');
    }

    $("#pivot-report-table").html('');

    CRM.api3(this.config.entityName, 'getcount', this.config.getCountParams(filterValueFrom, filterValueTo)).done(function(result) {
      var totalFiltered = parseInt(result.result, 10);

      if (!totalFiltered) {
        $('#pivot-report-preloader').addClass('hidden');

        if (this.config.filter) {
          $('#pivot-report-filters').removeClass('hidden');
        }

        CRM.alert('There are no items matching specified filter.');
      } else {
        that.total = totalFiltered;

        that.loadData({
          'keyvalue_from': filterValueFrom,
          'keyvalue_to': filterValueTo,
          'page': 0
        });
      }
    });
  };

  /**
   * Runs all data loading.
   */
  PivotTable.prototype.loadAllData = function() {
    this.resetData();

    if (this.config.filter) {
      this.pivotReportKeyValueFrom.val(null).trigger('change');
      $('#pivot-report-filters').addClass('hidden');
    }

    $("#pivot-report-table").html('');
    $('#pivot-report-preloader').removeClass('hidden');

    this.loadData({
      "keyvalue_from": null,
      "keyvalue_to": null,
      "page": 0
    });
  };

  /*
   * Inits Pivot Table with given data.
   *
   * @param {array} data
   */
  PivotTable.prototype.initPivotTable = function(data) {
    var that = this;

    $("#pivot-report-table").pivotUI(data, {
        rendererName: "Table",
        renderers: $.extend(
            $.pivotUtilities.renderers, 
            $.pivotUtilities.c3_renderers,
            $.pivotUtilities.export_renderers
        ),
        vals: ["Total"],
        rows: [],
        cols: [],
        aggregatorName: "Count",
        unusedAttrsVertical: false,
        rendererOptions: {
            c3: {
                size: {
                    width: parseInt($('#pivot-report-table').width() * 0.78, 10)
                }
            },
        },
        derivedAttributes: that.config.derivedAttributes,
        hiddenAttributes: that.config.hiddenAttributes,
    }, false);

    this.initDateFilters();
  };

  return PivotTable;
})(CRM.$);
