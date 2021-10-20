var $ = require('jquery');
var Utils = require('select2/utils');

module('Utils - GetUniqueElementId');

test('Adds a prefix to the existing ID if one exists', function (assert) {
    var $element = $('<select id="existing-id"></select>');

    var id = Utils.GetUniqueElementId($element[0]);

    assert.notEqual(id, 'existing-id');
    assert.notEqual(id.indexOf('existing-id'), -1);
});

test('Generated random ID is not a number', function (assert) {
    var $element = $('<select></select>');

    var id = Utils.GetUniqueElementId($element[0]);

    assert.ok(isNaN(id));
});

module('Utils - RemoveData');

test('The data-select2-id attribute is removed', function (assert) {
    var $element = $('<select data-select2-id="test"></select>');

    Utils.RemoveData($element[0]);

    assert.notEqual(
        $element.attr('data-select2-id'),
        'test',
        'The internal attribute was not removed when the data was cleared'
    );
});

test('The internal cache for the element is cleared', function (assert) {
    var $element = $('<select data-select2-id="test"></select>');

    Utils.__cache.test = {
        'foo': 'bar'
    };

    Utils.RemoveData($element[0]);

    assert.equal(Utils.__cache.test, null, 'The cache should now be empty');
});

test('Calling it on an element without data works', function (assert) {
    assert.expect(0);

    var $element = $('<select></select>');

    Utils.RemoveData($element[0]);), 'true');
});

test('option in disabled optgroup is disabled', function (assert) {
  var results = new Results($('<select></select>'), new Options({}));

  var $option = $('<optgroup disabled><option></option></optgroup>')
    .find('option');
  var option = results.option({
    element: $option[0]
  });

  assert.equal(option.getAttribute('aria-disabled'), 'true');
});

test('options are not selected by default', function (assert) {
  var results = new Results($('<select></select>'), new Options({}));

  var $option = $('<option></option>');
  var option = results.option({
    id: 'test',
    element: $option[0]
  });

  assert.notOk(option.classList.contains('select2-results__option--selected'));
});

test('options with children are given the group role', function(assert) {
  var results = new Results($('<select></select>'), new Options({}));

  var $option = $('<optgroup></optgroup>');
  var option = results.option({
    children: [{
      id: 'test'
    }],
    element: $option[0]
  });

  assert.equal(option.getAttribute('role'), 'group');
});

test('options with children have the aria-label set', function (assert) {
  var results = new Results($('<select></select>'), new Options({}));

  var $option = $('<optgroup></optgroup>');
  var option = results.option({
    children: [{
      id: 'test'
    }],
    element: $option[0],
    text: 'test'
  });

  assert.equal(option.getAttribute('aria-label'), 'test');
});

test('non-group options are given the option role', function (assert) {
  var results = new Results($('<select></select>'), new Options({}));

  var $option = $('<option></option>');
  var option = results.option({
    id: 'test',
    element: $option[0]
  });

  assert.equal(option.getAttribute('role'), 'option');
});
