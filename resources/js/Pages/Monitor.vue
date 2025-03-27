<script setup>

/**
 * Monitor - Page component
 *
 * This page is shown on the monitor.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
  import { Head } from '@inertiajs/vue3'

  // 3rd-party composables
  import { getSunset } from 'sunrise-sunset-js';
  import MonitorEntry from '@/Components/MonitorEntry.vue';
  import axios from 'axios';
  import PayloadView from '@/Components/PayloadView.vue';

// #endregion
// #region Props

  const props = defineProps({
    station_name: {
      type: String,
      required: true,
    },
    station_time: {
      type: String,
      required: true,
    },
    station_location: {
      type: Object,
      required: true,
    },
    monitor_zoom: {
      type: Number,
      required: true,
    },
  })

  // #region Header-Info

    const monitorTime = ref(null);
    const issue = ref('')
    const hasIssue = computed(() => (issue?.value?.length ?? 0) > 0)

    const updateMonitorTime = () => {
      monitorTime.value = new Date(Date.now())

      const monitorDOM = document.getElementById('monitor');
      if (!monitorDOM) { return }

      const sunset = getSunset(props.station_location.lat, props.station_location.long);
      if (monitorTime.value > sunset)
      {
        if (!monitorDOM.hasAttribute('night-mode'))
        {
          monitorDOM.setAttribute('night-mode', '');
        }
      }
      else
      {
        if (monitorDOM.hasAttribute('night-mode'))
        {
          monitorDOM.removeAttribute('night-mode');
        }
      }
    }

    // ##########################################

    const infoDateFormatter = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: 'short', weekday: 'short' });

    const infoClock = computed(() => monitorTime.value?.toTimeString().slice(0, 5) ?? '--:--')
    const infoDate = computed(() => {
      if (!monitorTime.value) { return ''}
      const parts = infoDateFormatter.formatToParts(monitorTime.value);
      const day = parts.find(part => part.type === 'day').value;
      const month = parts.find(part => part.type === 'month').value.replace('.','');
      const weekday = parts.find(part => part.type === 'weekday').value.replace('.','');
      return `${day}. ${month}, ${weekday}`
    })

  // #endregion
  // #region Content

    const contentData = ref({ infos: [], events: { active: [], imminent: [], upcoming: [] }, tasks: [], recurring: [], lastUpdated: 0 })
    const hasOnceUpdated = ref(false)
    const lastUpdated = ref(null)

    const hasTopics = computed(() => (contentData.value.infos.length + contentData.value.events.active.length + contentData.value.events.imminent.length) > 0)
    const hasTasks = computed(() => (contentData.value.tasks.length + contentData.value.recurring.length) > 0)
    const hasUpcomings = computed(() => contentData.value.events.upcoming.length > 0)
    const hasAnyToday = computed(() => hasTopics.value || hasTasks.value)

    const upcomingHeader = computed(() => hasUpcomings.value ? 'Anstehende Termine' : '&nbsp;')

    const updateContent = () => {

      axios.get('/monitor-poll')
        .then(response => {

          contentData.value = response.data
          hasOnceUpdated.value = true

          if (!lastUpdated.value) { lastUpdated.value = Date.now() }
          if ((contentData.value.lastupdated * 1000) >= lastUpdated.value) {
            window.location.reload()
          }

        })
        .catch(error => {
          issue.value = `Fehler: ${error}`
        })
        .finally(() => {
          handleOverflow()
          setTimeout(updateContent, 90000);
        });

    }

    // #region Content-Overflow

      const todayScrollingMargin = ref(0)
      const todayScrollingKeyframes = ref(null)

      const todayStyle = computed(() => {
        if (todayScrollingMargin.value < 0) {
          const duration = Math.abs(todayScrollingMargin.value / 3)
          return `animation: marquee ${duration}s linear infinite;`
        } else {
          return 'animation: none;'
        }
      })

      const handleOverflow = () => {

        const today = document.getElementById('today')
        if (!today || !todayScrollingKeyframes.value) { return }

        todayScrollingMargin.value = today.clientHeight - today.scrollHeight
        if (todayScrollingMargin.value < 0)
        {
          todayScrollingKeyframes.value.innerHTML = `
            @keyframes marquee {
              0%, 10%, 90%, 100% { transform: translateY(0); }
              40%, 60% { transform: translateY(${todayScrollingMargin.value}px); }
            }
          `
        }
        else
        {
          todayScrollingKeyframes.value.innerHTML = ''
        }

      }

    // #endregion

  // #endregion

// #endregion
// #region Lifecycle

  onMounted(() => {

    // start monitor clock
    const serverTimeOffset = Math.abs((new Date(props.station_time)).getTime() - Date.now());
    if (serverTimeOffset >= 5000)
    {
      issue.value = `Die Server- & Monitorzeit weichen ${serverTimeOffset/1000}s voneinander ab!`
    }

    setInterval(updateMonitorTime, 30000)
    updateMonitorTime()

    // today scrolling style element
    todayScrollingKeyframes.value = document.createElement('style')
    document.head.appendChild(todayScrollingKeyframes.value)

    // start polling
    updateContent()

  })

  onBeforeUnmount(() => {

    // remove today scrolling style element
    if (todayScrollingKeyframes.value && todayScrollingKeyframes.value.parentNode) {
      todayScrollingKeyframes.value.parentNode.removeChild(todayScrollingKeyframes.value)
    }

  })

// #endregion

</script>

<template>
  <Head title="Monitor" />
  <main id="monitor" :style="{ zoom: monitor_zoom }">
    <header>
      <name>{{ station_name }}</name>
      <info>
        <clock>{{ infoClock }}</clock>
        <date>{{ infoDate }}</date>
        <issues v-show="hasIssue">{{ issue }}</issues>
      </info>
    </header>
    <template v-if="hasOnceUpdated">
      <content id="today">
        <content id="todayScrollContainer" :style="todayStyle">
          <template v-if="hasTopics">
            <content-title>Aktuell</content-title>
            <content-list>
              <MonitorEntry :item="info" v-for="info in contentData.infos" />
              <MonitorEntry :item="event" v-for="event in contentData.events.imminent" />
              <MonitorEntry :item="event" v-for="event in contentData.events.active" />
            </content-list>
          </template>
          <template v-if="hasTasks">
            <content-title>Aufgaben</content-title>
            <content-list>
              <MonitorEntry :item="task" v-for="task in contentData.tasks" />
              <MonitorEntry :item="task" :showTiming="false" v-for="task in contentData.recurring" />
            </content-list>
          </template>
          <template v-if="!hasAnyToday">
            <content-title>Aktuell</content-title>
            <content-list>
              <PayloadView :payload="{ title: 'Alles erledigt!'}" />
            </content-list>
          </template>
        </content>
      </content>
      <content id="upcoming">
        <content-title v-html="upcomingHeader"></content-title>
        <content-list>
          <MonitorEntry :item="event" :show-type-icon="false" v-for="event in contentData.events.upcoming" />
        </content-list>
      </content>
    </template>
  </main>
</template>
<style lang="css">

html, body, #app {
  font-family: 'Fredoka' !important;
  margin: 0 !important;
  padding: 0 !important;
  width: 100% !important;
  height: 100% !important;
  font-size: 2vh !important;
}

</style>
<style lang="scss" scoped>

#monitor
{

  height: 100%;

  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  grid-template-rows: 10% 90%;
  gap: 0 0;
  grid-template-areas:
    "header header header"
    "today today upcoming";
  z-index: 1000;

  header
  {
    grid-area: header;

    background-color: var(--monitor-base-color);
    color: var(--monitor-contrast-color);

    display: flex;
    align-items: center;
    padding: var(--content-title-padding);
    font-family: 'Fredoka';

    name
    {
      font-size: 2rem;
      flex: 1;
    }
    info
    {
      * {
        display: block;
        text-align: right;
        line-height: 1;
      }

      clock { font-weight: 300; }
      date { font-size: 0.8rem; }
      issues { color: red; font-size: 0.7rem; }
    }
  }

  content
  {
    display: flex;
    flex-direction: column;

    background-color: var(--monitor-base-color);
    color: var(--monitor-contrast-color);

    content-title
    {
      padding: var(--content-title-padding);
      background-color: var(--monitor-contrast-color);
      color: var(--monitor-base-color);
      font-family: 'Fredoka';
      font-size: 0.9rem;
      font-weight: 500;
    }

    content-list
    {

      padding: 0 var(--content-list-padding);

      article
      {
        border-bottom: var(--monitor-border-thickness) solid var(--monitor-contrast-color);
      }

      article:last-child
      {
        border: none;
      }

    }

  }

  #today
  {

    grid-area: today;

    overflow: clip;
    position: relative;

    &::before
    {
      content: "";
      position: absolute;
      left: 0;
      top: 0;
      width: 100%;
      height: 1vh;
      background: linear-gradient(to bottom, var(--monitor-contrast-color-trans), transparent);
      pointer-events: none;
      z-index: 10;
    }
    &::after
    {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      width: 100%;
      height: 2vh;
      background: linear-gradient(to bottom, transparent, var(--monitor-base-color));
      pointer-events: none;
      z-index: 10;
    }

  }

  #todayScrollContainer
  {
    z-index: 9;
  }

  #upcoming
  {
    grid-area: upcoming;
    border-left: var(--monitor-border-thickness) solid var(--monitor-contrast-color) ;

    overflow: clip;
    position: relative;

    &::after
    {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      width: 100%;
      height: 33%;
      background: linear-gradient(to bottom, transparent, var(--monitor-base-color));
      pointer-events: none;
    }

  }

}

</style>