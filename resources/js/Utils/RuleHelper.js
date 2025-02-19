export const RuleHelper = {

  empty(v) { return (!!v && v.trim().length>0) || 'Du musst etwas eintragen.' },
  link(v) {
    if (!/^(https?:\/\/)([\w-]+(\.[\w-]+)+)([\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-])?$/i.test(v)) {
      return 'Du musst einen gültigen Link eingeben.'
    }
    return true
  },

};
