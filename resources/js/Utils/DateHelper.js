export const DateHelper = {

  getNthWeekValues(val) {
    const values = [
      { title: 'Ersten', value: 1 },
      { title: 'Letzten', value: 0 },
      { title: 'Zweiten', value: 2 },
      { title: 'Dritten', value: 3 },
      { title: 'Vierten', value: 4 },
    ]
    return val == null ? values : (values.find(e => e.value === val)?.title ?? '')
  },
  getDaysOfMonthValues(val) {
    const values = [
      { title: 'Ersten', value: 1 },
      { title: 'Letzten', value: 0 },
      { title: '2.', value: 2 },
      { title: '3.', value: 3 },
      { title: '4.', value: 4 },
      { title: '5.', value: 5 },
      { title: '6.', value: 6 },
      { title: '7.', value: 7 },
      { title: '8.', value: 8 },
      { title: '9.', value: 9 },
      { title: '10.', value: 10 },
      { title: '11.', value: 11 },
      { title: '12.', value: 12 },
      { title: '13.', value: 13 },
      { title: '14.', value: 14 },
      { title: '15.', value: 15 },
      { title: '16.', value: 16 },
      { title: '17.', value: 17 },
      { title: '18.', value: 18 },
      { title: '19.', value: 19 },
      { title: '20.', value: 20 },
      { title: '21.', value: 21 },
      { title: '22.', value: 22 },
      { title: '23.', value: 23 },
      { title: '24.', value: 24 },
      { title: '25.', value: 25 },
      { title: '26.', value: 26 },
      { title: '27.', value: 27 },
      { title: '28.', value: 28 },
      { title: '29.', value: 29 },
      { title: '30.', value: 30 },
      { title: '31.', value: 31 },
    ]
    return val == null ? values : (values.find(e => e.value === val)?.title ?? '')
  },
  getWeekdaysValues(val) {
    const values = [
      { title: 'Montag', value: 1 },
      { title: 'Dienstag', value: 2 },
      { title: 'Mittwoch', value: 3 },
      { title: 'Donnerstag', value: 4 },
      { title: 'Freitag', value: 5 },
      { title: 'Samstag', value: 6 },
      { title: 'Sonntag', value: 0 },
    ]
    return val == null ? values : (values.find(e => e.value === val)?.title ?? '')
  },

  isToday(date) {
    const today = new Date();
    return (
      date.getDate() === today.getDate() &&
      date.getMonth() === today.getMonth() &&
      date.getFullYear() === today.getFullYear()
    );
  },
  isTomorrow(date) {
    const today = new Date();
    const tomorrow = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
    return (
      date.getDate() === tomorrow.getDate() &&
      date.getMonth() === tomorrow.getMonth() &&
      date.getFullYear() === tomorrow.getFullYear()
    );
  },
  isSameDay(dateStart, dateEnd) {
    return (
      dateStart.getDate() === dateEnd.getDate() &&
      dateStart.getMonth() === dateEnd.getMonth() &&
      dateStart.getFullYear() === dateEnd.getFullYear()
    );
  },

  isNullOrPassed(date) {
    return !date || date < new Date()
  },

  formatDate(date) {

    if (this.isToday(date))
    {
      return `Heute`
    }
    else if (this.isTomorrow(date))
    {
      return `Morgen`
    }
    else
    {
      const today = new Date();
      const day = date.getDate().toString().padStart(2, '0');
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      return date.getFullYear() === today.getFullYear() ? `${day}.${month}` : `${day}.${month}.${date.getFullYear()}`;
    }

  },
  formatTime(date) {
    return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
  },
  formatDateTime(date, is_allday) {
    if (is_allday) {
      return this.formatDate(date)
    } else {
      return this.isToday(date) ? this.formatTime(date) : `${this.formatDate(date)} ${this.formatTime(date)}`
    }
  },
  formatDateRange(start, end, is_allday) {
    if (this.isSameDay(start, end)) {
      return is_allday ? this.formatDate(start) : `${this.formatDateTime(start, is_allday)} - ${this.formatTime(end)}`
    } else {
      return `${this.formatDateTime(start, is_allday)} - ${this.formatDateTime(end, is_allday)}`
    }
  },

};
