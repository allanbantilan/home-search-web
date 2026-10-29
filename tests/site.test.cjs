const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('payment estimate handles zero interest and invalid terms', () => {
  const listeners = {};
  const fields = {
    'down-payment': { value: '20', addEventListener: (_, callback) => { listeners.down = callback; } },
    'interest-rate': { value: '0', addEventListener() {} },
    'loan-years': { value: '20', addEventListener() {} },
    'monthly-payment': { textContent: '' },
  };
  const calculator = { dataset: { price: '1200000' }, querySelectorAll: () => Object.values(fields).slice(0, 3) };
  const document = {
    querySelector: selector => selector === '.calculator' ? calculator : null,
    querySelectorAll: () => [],
    getElementById: id => fields[id],
    addEventListener() {},
  };
  vm.runInNewContext(fs.readFileSync('property-search/assets/js/site.js', 'utf8'), {
    document, window: { HomeSearch: {} }, localStorage: { getItem: () => null },
    Intl, setTimeout, clearTimeout,
  });
  assert.equal(fields['monthly-payment'].textContent, '₱4,000');
  fields['down-payment'].value = '100';
  listeners.down();
  assert.equal(fields['monthly-payment'].textContent, 'Enter valid loan terms');
});
