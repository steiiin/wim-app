<script setup>

/**
 * PayloadView - Component
 *
 * Takes entry payload json and displays the data.
 *
 */

// #region Imports

// Vue composables
import { ref, computed } from 'vue'

// Local components
import { DateHelper } from '@/Utils/DateHelper';

// #endregion
// #region Props

const props = defineProps({
  payload: {
    type: Object,
    required: true,
  },
  showTypeIcon: {
    type: Boolean,
    default: true,
  },
  showTiming: {
    type: Boolean,
    default: true,
  }
})

const isInfo = computed(() => props.payload?.type === 'info')
const isEvent = computed(() => props.payload?.type === 'event')

const hasTimeIndication = computed(() => (props.payload?.time_start || props.payload?.time_end) && props.showTiming)

const hasBegun = ref(false)
const hasRange = ref(false)

const titleText = computed(() => !props.payload?.title ? 'Ohne Titel' : props.payload.title)
const timingText = computed(() => {

  if (!hasTimeIndication.value) return ''

  const { time_start, time_end, is_allday } = props.payload || {}
  const isAllDay = is_allday ?? false
  const start = time_start ? new Date(time_start) : null
  const end = time_end ? new Date(time_end) : null

  if (props.payload.type === 'event')
  {

    hasRange.value = !!end

    if (!end)
    {
      hasBegun.value = DateHelper.isToday(start)
      return DateHelper.formatDateTime(start, isAllDay)
    }
    else
    {
      if (DateHelper.isNullOrPassed(start))
      {
        hasBegun.value = true
        return `bis ${DateHelper.formatDateTime(end, is_allday)}`
      }
      else
      {
        hasBegun.value = false
        return DateHelper.formatDateRange(start, end, is_allday)
      }
    }
  }
  else if (props.payload.type === 'task')
  {
    hasBegun.value = true
    return `Bis ${DateHelper.formatTime(end)}`
  }
  else
  {
    hasBegun.value = true
    return ''
  }

})

const hasVehicle = computed(() => !!props.payload?.vehicle)
const hasMeta = computed(() => !!props.payload?.meta)
const hasDescription = computed(() => !!props.payload?.description)

// #endregion

</script>

<template>
  <article class="payload" :class="{
    'payload-no-pre': !showTypeIcon,
    'payload-fade': hasBegun && hasRange }">
    <article-pre>
      <v-icon v-if="isInfo" icon="mdi-information" />
      <template v-else-if="isEvent">
        <v-icon v-if="hasBegun" icon="mdi-calendar" />
        <v-icon v-else icon="mdi-calendar-clock" />
      </template>
      <v-icon v-else icon="mdi-check-outline" />
    </article-pre>
    <article-payload>
      <payload-title>
        <vehicle v-if="hasVehicle">{{ payload.vehicle }}</vehicle>{{ titleText }}
      </payload-title>
      <payload-meta v-if="hasMeta">{{ payload.meta }}</payload-meta>
      <payload-description v-if="hasDescription">{{ payload.description }}</payload-description>
      <payload-timing v-if="hasTimeIndication">
        <template v-if="!showTypeIcon || hasBegun">
          <v-icon v-if="hasBegun && hasRange" icon="mdi-clock-end" />
          <v-icon v-else icon="mdi-clock-outline" />
        </template>
        {{ timingText }}
      </payload-timing>
    </article-payload>
  </article>
</template>

<style lang="scss" scoped>
article {

  display: grid;
  grid-template-columns: calc(var(--content-list-padding)/2 + 1.5rem) 1fr;

  padding: 1rem 0;

  &-pre {

    display: flex;
    align-items: center;

    .v-icon {
      display: flex;
      justify-content: center;
      font-size: 1.5rem;
      height: 1.5rem;
    }

  }

  &-payload {

    display: flex;
    flex-direction: column;

    payload-title {
      display: inline-flex;
      flex-wrap: wrap;

      min-height: 1.5rem;
      align-items: center;
      gap: 1px 0.35rem;

      font-size: 1.1rem;
      font-weight: 500;
      line-height: 1;

      vehicle {
        display: inline-flex;
        align-items: center;

        background-color: var(--monitor-contrast-color);
        color: var(--monitor-base-color);
        height: 1.5rem;
        margin: 0;
        padding: 0 0.35rem;

        font-family: 'Fredoka';
        font-weight: bold;
        font-size: 0.8rem;
        text-transform: uppercase;
      }

    }

    payload-meta {
      text-transform: uppercase;
      font-weight: 600;
      font-size: 0.9rem;
      line-height: 1.1;
      font-family: "Inter";
    }

    payload-description {
      line-height: 1.1;
      opacity: 0.8;
    }

    payload-timing {

      display: flex;
      align-items: center;
      font-size: 0.9rem;
      font-weight: 500;
      text-transform: uppercase;

      line-height: 1rem;
      gap: .2rem;

      .v-icon {
        font-size: 1rem;
      }

    }

  }

}

.payload-no-pre
{
  grid-template-columns: 0 1fr;
  article-pre { opacity: 0; }
}

.payload-fade article-payload
{
  zoom: 0.8;
  transform-origin: left;

  & payload-meta { display: none; }
}

</style>