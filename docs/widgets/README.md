# Scriptable widgets (iOS)

## Metals / Markets
- Feed: `https://rc.arthsathi.com/metals.php?format=json`
- Compatible clients: Scriptable v18.5+, v22 (classier layout)
- Fields: `items.*.basePrice|totalPrice|change`, `fx.USDINR|EURINR|CNYINR|AEDINR.price|change`, `timestamp`

## Choghadiya / Panchang
- Feed: `https://rc.arthsathi.com/choghadiya.php?format=json&lat=&lon=&lang=&date=YYYY-MM-DD&tz=Asia/Kolkata`
- Compatible: Scriptable widget **v6.4.1**
- Required keys: `date` (or dateStr/isoDate), `current.name`, `rahu.{start,end,label}`, `tithi.{number,name,end}`, `sun.{rise,set}`
- Day navigation: server **honours** `&date=`; mismatched dates are rejected by the widget on purpose

