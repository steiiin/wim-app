export const infoIcons = [
  { title: 'Information', value: 'mdi-information' },
  { title: 'Warnung', value: 'mdi-alert' },
  { title: 'Wartung', value: 'mdi-wrench' },
  { title: 'Aufräumen', value: 'mdi-broom' },
  { title: 'Baustelle', value: 'mdi-boom-gate' },
  { title: 'Gewitter', value: 'mdi-lightning-bolt' },
]

export const payloadIcons = [
  ...infoIcons.map(({ value }) => value),
  'mdi-ambulance',
  'mdi-medical-bag',
  'mdi-hand-wash',
  'mdi-trash-can',
]
