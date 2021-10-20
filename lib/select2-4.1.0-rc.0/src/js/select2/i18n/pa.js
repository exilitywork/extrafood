define(function () {
  // Punjabi
  return {
    errorLoading: function () {
      return 'ਨਤੀਜੇ ਲੋਡ ਨਹੀਂ ਕੀਤੇ ਜਾ ਸਕਦੇ ।';
    },
    inputTooLong: function (args) {
      var overChars = args.input.length - args.maximum;

      var charCount = (overChars != 1) ? ' ਅੱਖਰਾਂ ਨੂੰ ' : ' ਅੱਖਰ ';

      var message = 'ਕ੍ਰਿਪਾ ਕਰਕੇ ' + overChars + charCount + 'ਮਿਟਾਓ ।';

      return message;
    },
    inputTooShort: function (args) {
      var remainingChars = args.minimum - args.input.length;

      var charCount = (remainingChars > 1) ? ' ਅੱਖਰਾਂ ' : ' ਅੱਖਰ ';

      /**
      * @param {string} messageXn';
      var message = 'Er ' + verb + ' maar ' + args.maximum + ' item';

      if (args.maximum != 1) {
        message += 's';
      }
      message += ' worden geselecteerd';

      return message;
    },
    noResults: function () {
      return 'Geen resultaten gevonden…';
    },
    searching: function () {
      return 'Zoeken…';
    },
    removeAllItems: function () {
      return 'Verwijder alle items';
    }
  };
});
