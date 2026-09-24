<script setup>
import { computed } from 'vue'
import { DateHelper } from '@/Utils/DateHelper'

const props = defineProps({
  item: { type: Object, required: true },
  now: { type: Date, required: true },
})
const start = computed(() => new Date(props.item.time_start))
const end = computed(() => props.item.time_end ? new Date(props.item.time_end) : null)
const prominent = computed(() => {
  const tomorrowEnd = new Date(props.now)
  tomorrowEnd.setDate(tomorrowEnd.getDate() + 1)
  tomorrowEnd.setHours(23, 59, 59, 999)
  return start.value <= tomorrowEnd
})
const icon = computed(() => {
  const customIcons = ['mdi-ambulance', 'mdi-medical-bag', 'mdi-hand-wash', 'mdi-information']
  if (customIcons.includes(props.item.icon)) return props.item.icon
  return start.value <= props.now ? 'mdi-calendar' : 'mdi-calendar-clock'
})
const timing = computed(() => {
  // The clock dependency refreshes relative date labels at midnight.
  const tomorrow = new Date(props.now)
  tomorrow.setDate(tomorrow.getDate() + 1)
  const dateLabel = (date) => {
    if (DateHelper.isSameDay(date, props.now)) return 'Heute'
    if (DateHelper.isSameDay(date, tomorrow)) return 'Morgen'
    return new Intl.DateTimeFormat('de-DE', {
      day: '2-digit', month: '2-digit',
      ...(date.getFullYear() !== props.now.getFullYear() ? { year: 'numeric' } : {}),
    }).format(date)
  }
  const sameDay = !end.value || DateHelper.isSameDay(start.value, end.value)
  if (sameDay) {
    const omitDate = DateHelper.isSameDay(start.value, props.now) || DateHelper.isSameDay(start.value, tomorrow)
    const date = omitDate ? '' : dateLabel(start.value)
    if (props.item.is_allday) return date
    const times = `${DateHelper.formatTime(start.value)}${end.value ? ` – ${DateHelper.formatTime(end.value)}` : ''}`
    return date ? `${date} ${times}` : times
  }

  const todayBegin = new Date(props.now)
  todayBegin.setHours(0, 0, 0, 0)
  if (start.value < todayBegin) {
    const date = dateLabel(end.value).toLowerCase()
    const until = props.item.is_allday
      ? date
      : `${DateHelper.isSameDay(end.value, props.now) ? '' : `${date} `}${DateHelper.formatTime(end.value)}`
    return `Läuft bis ${until}`
  }

  const endpoint = (date) => props.item.is_allday
    ? dateLabel(date)
    : `${dateLabel(date)} ${DateHelper.formatTime(date)}`
  return `${endpoint(start.value)} ${prominent.value ? 'BIS' : '–'} ${endpoint(end.value)}`
})
</script>

<template>
  <article class="event-view" :class="{ 'event-view--prominent': prominent }">
    <div class="event-identity">
      <v-icon :icon="icon" />
      <span v-if="item.vehicle" class="event-vehicle">{{ item.vehicle }}</span>
    </div>
    <div class="event-body">
      <h2>{{ item.title || 'Ohne Titel' }}</h2>
      <div v-if="item.meta" class="event-meta">{{ item.meta }}</div>
      <div v-if="item.description" class="event-description">{{ item.description }}</div>
      <div v-if="timing" class="event-timing">
        <v-icon icon="mdi-clock-outline" />
        <span>{{ timing }}</span>
      </div>
    </div>
  </article>
</template>

<style lang="scss" scoped>
.event-view {
  --event-size: 0.8rem;
  --event-padding: 0.65rem;
  display: grid;
  grid-template-columns: minmax(0, clamp(2.5rem, 12%, 5rem)) minmax(0, 1fr);
  gap: 0.75rem;
  align-items: center;
  padding-block: var(--event-padding);
  font-size: var(--event-size);
  &--prominent { --event-size: 0.9rem; --event-padding: 0.85rem; }
  &:not(.event-view--prominent) {
    opacity: 0.7;
  }
}
.event-identity {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35em;
  min-width: 0;
  .v-icon { font-size: 1.5em; }
}
.event-vehicle {
  max-width: 100%;
  padding: 0.2em 0.4em;
  background: var(--monitor-contrast-color);
  color: var(--monitor-base-color);
  font-size: 0.65em;
  font-weight: 600;
  line-height: 1.2;
  text-align: center;
  text-transform: uppercase;
  overflow-wrap: anywhere;
}
.event-body {
  min-width: 0;
  line-height: 1.2;
  overflow-wrap: anywhere;
  h2 { font-size: min(1em, var(--monitor-heading-size, 0.9rem)); line-height: 1.15; font-weight: 500; margin: 0; }
}
.event-timing {
  display: flex;
  align-items: center;
  gap: 0.2rem;
  margin-top: 0.3em;
  font-size: 0.8em;
  font-weight: 500;
  .v-icon { font-size: 1em; }
}
.event-meta {
  margin-top: 0.3em;
  font-family: 'Inter';
  font-size: 0.75em;
  font-weight: 600;
  text-transform: uppercase;
}
.event-description { margin-top: 0.25em; font-size: 0.85em; }
</style>
