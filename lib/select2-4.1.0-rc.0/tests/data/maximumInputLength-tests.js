module('Data adapters - Maximum input length');

var MaximumInputLength = require('select2/data/maximumInputLength');
var $ = require('jquery');
var Options = require('select2/options');
var Utils = require('select2/utils');

function MaximumInputStub () {
  this.called = false;
}

MaximumInputStub.prototype.query = function (params, callback) {
  this.called = true;
};

var MaximumInputData = Utils.Decorate(MaximumInputStub, MaximumInputLength);

test('0 never displays the notice', function (assert) {
  var zeroOptions = new Options({
    maximumInputLength: 0
  });

  var data = new MaximumInputData(null, zeroOptions);

  data.trigger = function () {
    assert.ok(false, 'No events should be triggered');
  };

  data.query({
    term: ''
  });

  assert.ok(data.called);

  data = new MaximumInputData(null, zeroOptions);

  data.query({
    term: 'test'
  });

  assert.ok(data.called);
});

test('< 0 never displays the notice', function (assert) {
  var negativeOptions = new Options({
    maximumInputLength: -1
  });

  var data = new MaximumInputData(null, negativeOptions);

  data.trigger = function () {
    assert.ok(false, 'No events should be triggered');
  };

  data.query({
    term: ''
  });

  assert.ok(data.called);

  data = new MaximumInputData(null, negativeOptions);

  data.query({
    term: 'test'
  });

  assert.ok(data.called);
});

test('triggers when input is too long', function (assert) {
  var options = new Options({
    maximumInputLength: 1
  });

  var data = new MaximumInputData(null, options);

  data.trigger = function () {
    assert.ok(true, 'The event should be triggered.');
  };

  data.query({
    term: 'no'
  });

  assert.ok(!data.called, 'The adapter should not be called');
});

test('does not trigger when equal', function (assert) {
  var options = new Options({
    maximumInputLength: 10
  });

  var data = new MaximumInputData(null, options);

  data.trigger = function () {
    assert.ok(false, 'The event should not be triggered.');
  };

  data.query({
    term: '1234567890'
  });

  assert.ok(data.called);
});

test('does not trigger when less', function (assert) {
  var options = new Options({
    maximumInputLength: 10
  });

  var data = new MaximumInputData(null, options);

  data.trigger = function () {
    assert.ok(false, 'The event should not be triggered.');
  };

  data.query({
    term: '123'
  });

  assert.ok(data.called);
});

test('works with null term', function (assert) {
  var options = new Options({
    maximumInputLength: 1
  });

  var data = new MaximumInputData(null, options);

  data.trigger = function () {
    assert.ok(false, 'The event should not be triggered');
  };

  data.query({});

  assert.ok(data.called);
});
ata = new ArrayData($select, arrayOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  data.select({
    id: '2',
    text: '2'
  });

  data.current(function (val) {
    assert.equal(
      val.length,
      1,
      'There should only be one option selected.'
    );

    var option = val[0];

    assert.equal(
      option.id,
      '2',
      'The id should match the original id from the array.'
    );

    assert.equal(
      option.text,
      '2',
      'The text should match the original text from the array.'
    );
  });
});

test('select works for single', function (assert) {
  var $select = $('#qunit-fixture .single-empty');

  var data = new ArrayData($select, arrayOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  assert.equal(
    $select.val(),
    'default',
    'There should already be a selection'
  );

  data.select({
    id: '1',
    text: 'One'
  });

  assert.equal(
    $select.val(),
    '1',
    'The selected value should be the same as the selected id'
  );
});

test('multiple sets the value', function (assert) {
  var $select = $('#qunit-fixture .multiple');

  var data = new ArrayData($select, arrayOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  assert.ok(
    $select.val() == null || $select.val().length == 0,
    'nothing should be selected'
  );

  data.select({
    id: 'default',
    text: 'Default'
  });

  assert.deepEqual($select.val(), ['default']);
});

test('multiple adds to the old value', function (assert) {
  var $select = $('#qunit-fixture .multiple');

  var data = new ArrayData($select, arrayOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  $select.val(['One']);

  assert.deepEqual($select.val(), ['One']);

  data.select({
    id: 'default',
    text: 'Default'
  });

  assert.deepEqual($select.val(), ['One', 'default']);
});

test('option tags are automatically generated', function (assert) {
  var $select = $('#qunit-fixture .single-empty');

  var data = new ArrayData($select, arrayOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  assert.equal(
    $select.find('option').length,
    4,
    'An <option> element should be created for each object'
  );
});

test('automatically generated option tags have a result id', function (assert) {
  var $select = $('#qunit-fixture .single-empty');

  var data = new ArrayData($select, arrayOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  data.select({
    id: 'default'
  });

  assert.ok(
    Utils.GetData($select.find(':selected')[0], 'data')._resultId,
    '<option> default should have a result ID assigned'
  );
});

test('option tags can receive new data', function(assert) {
  var $select = $('#qunit-fixture .single');

  var data = new ArrayData($select, extraOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  assert.equal(
    $select.find('option').length,
    2,
    'Only one more <option> element should be created'
  );

  data.select({
    id: 'default'
  });

  assert.ok(
    Utils.GetData($select.find(':selected')[0], 'data').extra,
    '<option> default should have new data'
  );

  data.select({
    id: 'One'
  });

  assert.ok(
    Utils.GetData($select.find(':selected')[0], 'data').extra,
    '<option> One should have new data'
  );
});

test('optgroup tags can also be generated', function (assert) {
  var $select = $('#qunit-fixture .single-empty');

  var data = new ArrayData($select, nestedOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  assert.equal(
    $select.find('option').length,
    1,
    'An <option> element should be created for the one selectable object'
  );

  assert.equal(
    $select.find('optgroup').length,
    2,
    'An <optgroup> element should be created for the two with children'
  );
});

test('optgroup tags have the right properties', function (assert) {
  var $select = $('#qunit-fixture .single-empty');

  var data = new ArrayData($select, nestedOptions);

  var container = new MockContainer();
  data.bind(container, $('<div></div>'));

  var $group = $select.children('optgroup');

  assert.equal(
    $group.prop('label'),
    'Default',
    'An `<optgroup>` label should match the text property'
  );

  assert.equal(
    $group.children().length,
    1,
    'The <optgroup> should have one child under it'
  );
});

test('existing selections are respected on initialization', function (assert) {
   var $select = $(
     '<select>' +
        '<option>First</option>' +
        '<option selected>Second</option>' +
      '</select>'
    );

    var options = new Options({
      data: [
        {
          id: 'Second',
          text: 'Second'
        },
        {
          id: 'Third',
          text: 'Third'
        }
      ]
    });

    assert.equal($select.val(), 'Second');

    var data = new ArrayData($select, options);

    var container = new MockContainer();
    data.bind(container, $('<div></div>'));

    assert.equal($select.val(), 'Second');
});