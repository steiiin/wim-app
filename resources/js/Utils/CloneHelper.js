export const CloneHelper = {

  payload(obj) { return JSON.parse(JSON.stringify(obj)) },
  date(date) { return new Date(date) },
  string(str) { return !str ? '' : (' ' + str).slice(1) },
  obj(obj) { return { ...obj }},

};
