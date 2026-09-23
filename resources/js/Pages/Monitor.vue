<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Head } from '@inertiajs/vue3'
import { getSunrise, getSunset } from 'sunrise-sunset-js'
import axios from 'axios'
import EventView from '@/Components/EventView.vue'
import PayloadView from '@/Components/PayloadView.vue'

const props = defineProps({
  station_name: { type: String, required: true },
  station_time: { type: String, required: true },
  station_location: { type: Object, required: true },
  monitor_zoom: { type: Number, required: true },
})

const monitorTime = ref(new Date())
const nightMode = ref(false)
const updateMonitorTime = () => {
  monitorTime.value = new Date()
  const { lat, long } = props.station_location
  nightMode.value = monitorTime.value > getSunset(lat, long) || monitorTime.value < getSunrise(lat, long)
}
const infoDateFormatter = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: 'short', weekday: 'short' })
const infoClock = computed(() => monitorTime.value.toTimeString().slice(0, 5))
const infoDate = computed(() => {
  const parts = infoDateFormatter.formatToParts(monitorTime.value)
  const part = (type) => parts.find(part => part.type === type).value.replace('.', '')
  return `${part('day')}. ${part('month')}, ${part('weekday')}`
})

const contentData = ref({ infos: [], events: { active: [], imminent: [], upcoming: [] }, tasks: [], recurring: [] })
const hasOnceUpdated = ref(false)
const eventGroups = computed(() => [
  { key: 'active', label: 'HEUTE' },
  { key: 'imminent', label: 'MORGEN' },
  { key: 'upcoming', label: 'ANSTEHEND' },
].map(group => ({
  ...group,
  events: [...contentData.value.events[group.key]]
    .sort((a, b) => new Date(a.time_start) - new Date(b.time_start)),
})).filter(group => group.events.length))
const information = computed(() => [
  ...contentData.value.infos.map(item => ({ item, showTiming: true })),
  ...contentData.value.tasks.map(item => ({ item, showTiming: true })),
  ...contentData.value.recurring.map(item => ({ item, showTiming: false })),
])

const eventsContent = ref(null)
const eventsList = ref(null)
const upcomingGroup = ref(null)
const upcomingLabelTop = ref('50%')
const setUpcomingGroup = (element) => { upcomingGroup.value = element }
let eventsResizeObserver
const updateUpcomingLabelPosition = () => {
  if (!eventsContent.value || !upcomingGroup.value) {
    upcomingLabelTop.value = '50%'
    return
  }
  const group = upcomingGroup.value.getBoundingClientRect()
  const visibleHeight = Math.min(group.height, eventsContent.value.getBoundingClientRect().bottom - group.top)
  // A percentage keeps the visible midpoint correct at any monitor zoom.
  upcomingLabelTop.value = visibleHeight > 0 && group.height > 0
    ? `${visibleHeight / group.height * 50}%`
    : '50%'
}
const observeEventLayout = () => {
  if (!eventsResizeObserver) return
  eventsResizeObserver.disconnect()
  for (const element of [eventsContent.value, eventsList.value, upcomingGroup.value]) {
    if (element) eventsResizeObserver.observe(element)
  }
  updateUpcomingLabelPosition()
}
watch([eventsContent, eventsList, upcomingGroup, eventGroups, () => props.monitor_zoom], observeEventLayout, { flush: 'post' })

let lastUpdated = null
let clockTimer
let pollTimer
const pollController = new AbortController()
const updateContent = async () => {
  try {
    const response = await axios.get('/monitor-poll', { signal: pollController.signal })
    if (pollController.signal.aborted) return
    contentData.value = response.data
    hasOnceUpdated.value = true
    lastUpdated ??= Date.now()
    if (contentData.value.lastupdated * 1000 >= lastUpdated) window.location.reload()
  } catch (error) {
    if (!axios.isCancel(error)) console.error(error)
  } finally {
    if (!pollController.signal.aborted) pollTimer = setTimeout(updateContent, 90000)
  }
}

onMounted(() => {
  eventsResizeObserver = new ResizeObserver(updateUpcomingLabelPosition)
  observeEventLayout()
  const serverTimeOffset = Math.abs(new Date(props.station_time).getTime() - Date.now())
  if (serverTimeOffset >= 2700000) {
    console.error(`Die Server- & Monitorzeit weichen ${serverTimeOffset / 1000}s voneinander ab!`)
  }
  updateMonitorTime()
  clockTimer = setInterval(updateMonitorTime, 30000)
  updateContent()
})
onBeforeUnmount(() => {
  eventsResizeObserver?.disconnect()
  clearInterval(clockTimer)
  clearTimeout(pollTimer)
  pollController.abort()
})
</script>

<template>
  <Head title="Monitor" />
  <main id="monitor" :class="{ 'has-information': information.length }" :night-mode="nightMode ? '' : null" :style="{ zoom: monitor_zoom, '--monitor-zoom': monitor_zoom }">
    <header>
      <div class="station-name">{{ station_name }}</div>
      <div class="station-info">
        <div class="clock">{{ infoClock }}</div>
        <div class="date">{{ infoDate }}</div>
      </div>
    </header>
    <section id="events" class="monitor-panel" aria-labelledby="events-heading">
      <h1 id="events-heading">TERMINE</h1>
      <div ref="eventsContent" class="panel-content events-content">
        <template v-if="hasOnceUpdated">
          <div v-if="eventGroups.length" ref="eventsList" class="events-list">
            <section v-for="group in eventGroups" :key="group.key" :ref="group.key === 'upcoming' ? setUpcomingGroup : undefined" class="event-group" :class="{ 'event-group--single': group.events.length === 1 }" :aria-labelledby="`events-${group.key}-heading`">
              <h2 :id="`events-${group.key}-heading`" class="event-group-banner" :class="`event-group-banner--${group.key}`" :style="{ '--event-label-top': group.key === 'upcoming' ? upcomingLabelTop : null }">
                <span>{{ group.label }}</span>
              </h2>
              <div class="event-group-items">
                <EventView v-for="(event, index) in group.events" :key="index" :item="event" :now="monitorTime" />
              </div>
            </section>
          </div>
          <p v-else class="empty-state">Keine Termine</p>
        </template>
      </div>
    </section>
    <section v-if="information.length" id="information" class="monitor-panel" aria-labelledby="information-heading">
      <h1 id="information-heading">INFORMATIONEN</h1>
      <div class="panel-content information-list">
        <template v-if="hasOnceUpdated">
          <PayloadView v-for="(entry, index) in information" :key="index" :payload="entry.item" :show-timing="entry.showTiming" />
        </template>
      </div>
    </section>
  </main>
</template>

<style>
html, body, #app {
  font-family: 'Fredoka' !important;
  margin: 0 !important;
  padding: 0 !important;
  width: 100% !important;
  height: 100% !important;
  font-size: 1.8vh !important;
}
</style>

<style lang="scss" scoped>
#monitor {

  --monitor-heading-size: 0.9rem;

  // Compensate for CSS zoom so the kiosk always fits the physical viewport.
  width: calc(100vw / var(--monitor-zoom));
  height: calc(100vh / var(--monitor-zoom));
  overflow: clip;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  grid-template-rows: 10% minmax(0, 1fr);
  background: var(--monitor-base-color);
  color: var(--monitor-contrast-color);

  &.has-information { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }

  header {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    padding: var(--content-title-padding);
    min-width: 0;
  }
  .station-name { font-size: 2rem; flex: 1; min-width: 0; }
  .station-info { text-align: right; line-height: 1; }
  .clock { font-weight: 300; }
  .date { font-size: 0.8rem; }
}
.monitor-panel {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  overflow: clip;

  h1 {
    flex: none;
    margin: 0;
    padding: var(--content-title-padding);
    background: var(--monitor-contrast-color);
    color: var(--monitor-base-color);
    font-size: var(--monitor-heading-size);
    font-weight: 500;
  }
}
.panel-content {
  flex: 1;
  min-height: 0;
  min-width: 0;
  overflow: clip;
  position: relative;
  padding: 0 var(--content-list-padding);

  &::after {
    content: '';
    position: absolute;
    inset: auto 0 0;
    height: 2vh;
    background: linear-gradient(to bottom, transparent, var(--monitor-base-color));
    pointer-events: none;
    z-index: 2;
  }
}
#information { border-left: var(--monitor-border-thickness) solid var(--monitor-contrast-color); }
.information-list :deep(article),
.event-group-items :deep(article) {
  overflow-wrap: anywhere;
  border-bottom: var(--monitor-border-thickness) solid var(--monitor-contrast-color);
  &:last-child { border-bottom: none; }
}
.information-list :deep(payload-title) { font-size: var(--monitor-heading-size); }
.information-list :deep(payload-meta),
.information-list :deep(payload-description) { font-size: 0.8rem; }
.empty-state { margin: 1rem 0; font-size: var(--monitor-heading-size); }
.events-content {
  padding: 0;

  &::after { left: 2rem; }
  .empty-state { margin-inline: var(--content-list-padding); }
}
.event-group {
  display: grid;
  grid-template-columns: 2rem minmax(0, 1fr);

  &--single {
    min-height: 6rem;

    .event-group-items {
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
  }

  &:not(:last-child) .event-group-items {
    border-bottom: var(--monitor-border-thickness) solid var(--monitor-contrast-color);
  }
}
.event-group-banner {
  position: relative;
  margin: 0;
  background-color: #000;
  color: #fff;
  font-size: 0.8rem;
  font-weight: 500;
  line-height: 1.2;

  &--imminent,
  &--upcoming {
    background-image: repeating-linear-gradient(
      135deg,
      transparent 0,
      transparent calc(var(--hatch-spacing) - 1px),
      rgba(255, 255, 255, 0.35) calc(var(--hatch-spacing) - 1px),
      rgba(255, 255, 255, 0.35) var(--hatch-spacing)
    );
  }
  &--imminent { --hatch-spacing: 0.5rem; }
  &--upcoming { --hatch-spacing: 0.25rem; }

  span {
    position: absolute;
    top: var(--event-label-top, 50%);
    left: 50%;
    padding: 0.15rem 0.3rem;
    background: #000;
    white-space: nowrap;
    transform: translate(-50%, -50%) rotate(-90deg);
  }
}
.event-group-items {
  min-width: 0;
  padding-inline: var(--content-list-padding);
}
</style>
