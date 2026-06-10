const store = await import('./src/state/store.js');
const { deviceVM } = await import('./src/lib/vm.js');
const vms = store.getDevices().map(deviceVM);
const counts = {};
for (const v of vms) counts[v.status] = (counts[v.status]||0)+1;
console.log('statuses in list:', counts);
console.log('total:', vms.length);
