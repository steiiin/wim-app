<script setup>

/**
 * Admin - Page component
 *
 * This page enables the user to edit the displayed data.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import { Head, router } from '@inertiajs/vue3'

  // 3rd-party composables
  import axios from 'axios'

  // Local components
  import StationSettingsDialog from '@/Dialogs/StationSettingsDialog.vue'
  import ModuleTrashDialog from '@/Dialogs/ModuleTrashDialog.vue'
  import ModuleSharepointDialog from '@/Dialogs/ModuleSharepointDialog.vue'

  import EditInfoDialog from '@/Dialogs/EditInfoDialog.vue'
  import EditEventDialog from '@/Dialogs/EditEventDialog.vue'
  import EditTaskDialog from '@/Dialogs/EditTaskDialog.vue'
  import EditRecurringDialog from '@/Dialogs/EditRecurringDialog.vue'
  import DeleteDialog from '@/Dialogs/DeleteDialog.vue'

  // Local composables
  import { DateHelper } from '@/Utils/DateHelper'

// #endregion
// #region Props

  const props = defineProps({
    station_name: {
      type: String,
      required: true,
    },
    station_location: {
      type: Object,
      required: true,
    },
    infos: {
      type: Array,
      required: true,
    },
    events: {
      type: Array,
      required: true,
    },
    tasks: {
      type: Array,
      required: true,
    },
    recurrings: {
      type: Array,
      required: true,
    },
    moduleTrash: {
      type: Object,
      required: true,
    },
    moduleSharepoint: {
      type: Object,
      required: true,
    }
  })

  const infoData = computed(() => props.infos.map(e => ({ ...e,
    from: e.from ? new Date(e.from) : null,
    until: e.until ? new Date(e.until) : null,
  })).sort((a, b) => {

    // 1. is_permanent: true first
    if (a.is_permanent !== b.is_permanent) {
      return a.is_permanent ? -1 : 1;
    }

    // 2. Compare until dates (ascending).
    //    If both have dates, subtract them.
    //    If one is null, assume non-null dates come first.
    if (a.until && b.until) {
      const diff = a.until - b.until
      if (diff !== 0) return diff;
    } else if (a.until || b.until) {
      return a.until ? -1 : 1;
    }

    // 3. Compare payload properties (using payload.ab).
    return a.payload.ab.localeCompare(b.payload.ab)

  }))
  const hasInfoData = computed(() => props.infos.length > 0)

  const eventData = computed(() => props.events.map(e => ({ ...e,
    start: e.start ? new Date(e.start) : null,
    until: e.until ? new Date(e.until) : null,
  })).sort((a, b) => {

    // 1. Compare until dates (ascending).
    const dateA = a.until || a.start
    const dateB = b.until || b.start
    const diff = dateA - dateB
    if (diff !== 0) return diff;

    // 2. Compare payload properties (using payload.ab).
    return a.payload.ab.localeCompare(b.payload.ab)

  }))
  const hasEventData = computed(() => props.events.length > 0)

  const taskData = computed(() => props.tasks.map(e => ({ ...e,
    dueto: e.dueto ? new Date(e.dueto) : null,
    from: e.from ? new Date(e.from) : null,
  })).sort((a, b) => {

    // 1. Compare until dates (ascending).
    const diff = a.dueto - b.dueto
    if (diff !== 0) return diff;

    // 2. Compare payload properties (using payload.ab).
    return a.payload.ab.localeCompare(b.payload.ab)

  }))
  const hasTaskData = computed(() => props.tasks.length > 0)

  const recurringOrder = ["daily", "weekly", "monthly-day", "monthly-weekday"]
  const recurringData = computed(() => props.recurrings.map(e => ({ ...e,
    from: e.from ? new Date(e.from) : null,
    dueto: e.dueto ? new Date(e.dueto) : null,
  })).sort((a, b) => {

    // 1. Sort by recurrence_type order
    const typeA = recurringOrder.indexOf(a.recurrence_type);
    const typeB = recurringOrder.indexOf(b.recurrence_type);
    if (typeA !== typeB) return typeA - typeB;

    // 2. For the same recurrence_type, apply type-specific sorting:
    if (a.recurrence_type === "daily") {
      // For "daily": sort by dueto
      if (a.dueto.getTime() !== b.dueto.getTime()) return a.dueto - b.dueto;
    } else if (a.recurrence_type === "weekly") {
      // For "weekly": sort by weekday (number) then by dueto
      if (a.weekday !== b.weekday) return a.weekday - b.weekday;
      if (a.dueto.getTime() !== b.dueto.getTime()) return a.dueto - b.dueto;
    } else if (a.recurrence_type === "monthly-day") {
      // For "monthly-day": sort by nth_day then by dueto
      if (a.nth_day !== b.nth_day) return a.nth_day - b.nth_day;
      if (a.dueto.getTime() !== b.dueto.getTime()) return a.dueto - b.dueto;
    } else if (a.recurrence_type === "monthly-weekday") {
      // For "monthly-weekday": sort by weekday then by dueto
      if (a.weekday !== b.weekday) return a.weekday - b.weekday;
      if (a.dueto.getTime() !== b.dueto.getTime()) return a.dueto - b.dueto;
    }

    // 3. If still equal, sort by payload
    return a.payload.ab.localeCompare(b.payload.ab);

  }))
  const hasRecurringData = computed(() => props.recurrings.length > 0)

// #endregion
// #region Navigation

  // Router-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // Routes
  function logout() {
    debugger
    // router.get('/logout')
  }

// #endregion

// #region StationSettings

  const stationSettingsDialog = ref(null)
  const changeStationSettings = async () => {
    await stationSettingsDialog.value.open({
      station_name: props.station_name,
      station_location: props.station_location
    })
  }

// #endregion
// #region Module: Trash

  const moduleTrashDialog = ref(null)
  const changeModuleTrash = async () => {
    await moduleTrashDialog.value.open({
    })
  }

// #endregion
// #region Module: Sharepoint

const moduleSharepointDialog = ref(null)
  const changeModuleSharepoint = async () => {
    await moduleSharepointDialog.value.open({
      ...props.moduleSharepoint
    })
  }

// #endregion

const deleteDialog = ref(null)

// #region Info

  const editInfoDialog = ref(null)

  const createInfo = async () => {
    await editInfoDialog.value.open()
  }
  const editInfo = async (item) => {
    await editInfoDialog.value.open({ edit: true, ...item })
  }
  const deleteInfo = async (item) => {
    await deleteDialog.value.open({
      id: item.id,
      route: 'info',
      payload: item.payload,
    })
  }

  // #region Table

  const infosHeaders = [
    { title: 'Inhalt', value: 'payload' },
    { title: 'Sichtbarkeit', value: 'visible' },
    { title: '', key: 'actions', sortable: false, align: 'end', cellClass: 'action-column' },
  ]

  const convertInfoVisibility = (from, until) => {

    const hasBegun = DateHelper.isNullOrPassed(from)

    if (DateHelper.isToday(until))
    {
      return 'Nur noch <b>Heute</b>'
    }

    if (hasBegun)
    {
      return `Ist Sichtbar, bis <b>${DateHelper.formatDate(until)}</b>`
    }
    else
    {
      return `Ab <b>${DateHelper.formatDate(from)}</b> bis <b>${DateHelper.formatDate(until)}</b>`
    }

  }

  // #endregion

// #endregion
// #region Event

  const editEventDialog = ref(null)

  const createEvent = async () => {
    await editEventDialog.value.open()
  }
  const editEvent = async (item) => {
    await editEventDialog.value.open({ edit: true, ...item })
  }
  const deleteEvent = async (item) => {
    await deleteDialog.value.open({
      id: item.id,
      route: 'event',
      payload: item.payload,
    })
  }

  // #region Table

    const eventsHeaders = [
      { title: 'Inhalt', value: 'payload' },
      { title: 'Zeiten', value: 'visible' },
      { title: '', key: 'actions', sortable: false, align: 'end', cellClass: 'action-column' },
    ]

    const convertEventVisibility = (start, until, is_allday) => {

      const isRange = !!until
      const hasBegun = DateHelper.isNullOrPassed(start)

      if (!isRange && hasBegun) { return is_allday ? 'Nur noch <b>Heute</b>' : 'Bereits <b>vergangen</b>' }
      if (isRange)
      {
        return is_allday
          ? `Von <b>${DateHelper.formatDate(start)}</b> bis <b>${DateHelper.formatDate(until)}</b>`
          : `Von <b>${DateHelper.formatDate(start)} ${DateHelper.formatTime(start)}</b> bis <b>${DateHelper.formatDate(until)} ${DateHelper.formatTime(until)}</b>`
      }
      else
      {
        return is_allday
          ? `<b>${DateHelper.formatDate(start)}</b>`
          : `<b>${DateHelper.formatDate(start)} ${DateHelper.formatTime(start)}</b>`
      }

    }

  // #endregion

// #endregion
// #region Task

  const editTaskDialog = ref(null)

  const createTask = async () => {
    await editTaskDialog.value.open()
  }
  const editTask = async (item) => {
    await editTaskDialog.value.open({ edit: true, ...item })
  }
  const deleteTask = async (item) => {
    await deleteDialog.value.open({
      id: item.id,
      route: 'task',
      payload: item.payload,
    })
  }

  // #region Table

    const tasksHeaders = [
      { title: 'Inhalt', value: 'payload' },
      { title: 'Fällig', value: 'visible' },
      { title: '', key: 'actions', sortable: false, align: 'end', cellClass: 'action-column' },
    ]

    const convertTaskVisibility = (dueto) => {

      const isActive = DateHelper.isToday(dueto)
      return `${isActive ? 'Ist Aktiv, ': ''}${DateHelper.formatDate(dueto)} ${DateHelper.formatTime(dueto)}`

    }

  // #endregion

// #endregion
// #region Recurring

  const editRecurringDialog = ref(null)

  const createRecurring = async () => {
    await editRecurringDialog.value.open()
  }
  const editRecurring = async (item) => {
    await editRecurringDialog.value.open({ edit: true, ...item })
  }
  const deleteRecurring = async (item) => {
    await deleteDialog.value.open({
      id: item.id,
      route: 'recurring',
      payload: item.payload,
    })
  }

  // #region Table

    const recurringsHeaders = [
      { title: 'Inhalt', value: 'payload' },
      { title: 'Regeln', value: 'visible' },
      { title: '', key: 'actions', sortable: false, align: 'end', cellClass: 'action-column' },
    ]

    const convertRecurringVisibility = (from, dueto, type, weekday, nth_day) => {

      const formattedFrom = DateHelper.formatTime(from)
      const formattedDueto = DateHelper.formatTime(dueto)

      if (type === 'daily') { return `<b>Täglich</b> - ${formattedFrom} bis ${formattedDueto}` }
      else if (type === 'weekly') { return `Jede Woche am <b>${DateHelper.getWeekdaysValues(weekday)}</b> - ${formattedFrom} bis ${formattedDueto}` }
      else if (type === 'monthly-day') { return `Jeden Monat den <b>${DateHelper.getDaysOfMonthValues(nth_day)} Tag</b> - ${formattedFrom} bis ${formattedDueto}`}
      else if (type === 'monthly-weekday') { return `Jeden <b>${DateHelper.getNthWeekValues(nth_day)} ${DateHelper.getWeekdaysValues(weekday)}</b> im Monat - ${formattedFrom} bis ${formattedDueto}`}

    }

  // #endregion

// #endregion

// #region Lifecycle

  let heartbeatTimer = null

  const heartbeat = () => {
    axios.get('/admin-heartbeat')
    .catch(error => {
      if (error.response && error.response.status === 401) {
        router.visit('/login')
      }
    });
  }

  const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
      heartbeat()
    }
  }

  onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    heartbeatTimer = setInterval(heartbeat, 300000)
  })

  onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    clearInterval(heartbeatTimer)
  })

// #endregion

</script>

<template>
  <Head title="Admin" />
  <main id="admin">
    <header>
      <v-toolbar extended color="black">
        <v-toolbar-title>WIM-Admin</v-toolbar-title>
        <v-spacer></v-spacer>
        <v-btn icon @click="logout">
          <v-icon>mdi-logout</v-icon>
        </v-btn>
      </v-toolbar>
    </header>
    <content>
      <v-card>
        <v-card-title class="mb-n4">Wache</v-card-title>
        <v-card-subtitle>Allgemeine Einstellungen</v-card-subtitle>
        <v-card-text>
          <ul class="admin-info">
            <li><pre>Name:    </pre>{{ station_name }}</li>
            <li><pre>Standort:</pre>{{ `${station_location.lat}, ${station_location.long}` }}</li>
          </ul>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="changeStationSettings" text="Ändern"></v-btn>
        </v-card-actions>
      </v-card>

      <v-card>
        <v-card-title class="mb-n4">Einträge</v-card-title>
        <v-card-subtitle>Einträge auf dem Monitor von Hand bearbeiten</v-card-subtitle>
        <v-card-text>
          <v-expansion-panels color="black">
            <v-expansion-panel>
              <v-expansion-panel-title>Mitteilungen</v-expansion-panel-title>
              <v-expansion-panel-text>

                <v-toolbar density="compact">
                  <v-btn variant="tonal" class="mx-2"
                    @click="createInfo">Neue Mitteilung
                  </v-btn>
                </v-toolbar>
                <v-data-table :items="infoData" v-if="hasInfoData"
                  :headers="infosHeaders"
                  :hide-default-footer="true" :items-per-page="999">
                  <template v-slot:item.payload="{ item }">
                    {{ item.payload.title }}
                  </template>
                  <template v-slot:item.visible="{ item }">
                    <span v-if="item.is_permanent"><b>Dauerhaft</b></span>
                    <span v-else v-html="convertInfoVisibility(item.from, item.until)"></span>
                  </template>
                  <template v-slot:item.actions="{ item }">
                    <v-btn variant="text" icon="mdi-pencil" @click="editInfo(item)"></v-btn>
                    <v-btn variant="text" icon="mdi-delete" @click="deleteInfo(item)"></v-btn>
                  </template>
                </v-data-table>

              </v-expansion-panel-text>
            </v-expansion-panel>
            <v-expansion-panel>
              <v-expansion-panel-title>Termine</v-expansion-panel-title>
              <v-expansion-panel-text>

                <v-toolbar density="compact">
                  <v-btn variant="tonal" class="mx-2"
                    @click="createEvent">Neuer Termin
                  </v-btn>
                </v-toolbar>
                <v-data-table :items="eventData" v-if="hasEventData"
                  :headers="eventsHeaders" :hide-default-footer="true" :items-per-page="999">
                  <template v-slot:item.payload="{ item }">
                    {{ item.payload.title }}
                  </template>
                  <template v-slot:item.visible="{ item }">
                    <span v-html="convertEventVisibility(item.start, item.until, item.is_allday)"></span>
                  </template>
                  <template v-slot:item.actions="{ item }">
                    <v-btn variant="text" icon="mdi-pencil" @click="editEvent(item)"></v-btn>
                    <v-btn variant="text" icon="mdi-delete" @click="deleteEvent(item)"></v-btn>
                  </template>
                </v-data-table>

              </v-expansion-panel-text>
            </v-expansion-panel>
            <v-expansion-panel>
              <v-expansion-panel-title>Einzelne Aufgabe</v-expansion-panel-title>
              <v-expansion-panel-text>

                <v-toolbar density="compact">
                  <v-btn variant="tonal" class="mx-2"
                    @click="createTask">Neue Aufgabe
                  </v-btn>
                </v-toolbar>
                <v-data-table :items="taskData" v-if="hasTaskData"
                  :headers="tasksHeaders" :hide-default-footer="true" :items-per-page="999">
                  <template v-slot:item.payload="{ item }">
                    {{ item.payload.title }}
                  </template>
                  <template v-slot:item.visible="{ item }">
                    <span v-html="convertTaskVisibility(item.dueto)"></span>
                  </template>
                  <template v-slot:item.actions="{ item }">
                    <v-btn variant="text" icon="mdi-pencil" @click="editTask(item)"></v-btn>
                    <v-btn variant="text" icon="mdi-delete" @click="deleteTask(item)"></v-btn>
                  </template>
                </v-data-table>

              </v-expansion-panel-text>
            </v-expansion-panel>
            <v-expansion-panel>
              <v-expansion-panel-title>Tagesaufgaben</v-expansion-panel-title>
              <v-expansion-panel-text>

                <v-toolbar density="compact">
                  <v-btn variant="tonal" class="mx-2"
                    @click="createRecurring">Neue Tagesaufgabe
                  </v-btn>
                </v-toolbar>
                <v-data-table :items="recurringData" v-if="hasRecurringData"
                  :headers="recurringsHeaders" :hide-default-footer="true" :items-per-page="999">
                  <template v-slot:item.payload="{ item }">
                    {{ item.payload.title }}
                  </template>
                  <template v-slot:item.visible="{ item }">
                    <span v-html="convertRecurringVisibility(item.from, item.dueto, item.recurrence_type, item.weekday, item.nth_day)"></span>
                  </template>
                  <template v-slot:item.actions="{ item }">
                    <v-btn variant="text" icon="mdi-pencil" @click="editRecurring(item)"></v-btn>
                    <v-btn variant="text" icon="mdi-delete" @click="deleteRecurring(item)"></v-btn>
                  </template>
                </v-data-table>

              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card-text>
      </v-card>

      <v-card>
        <v-card-title class="mb-n4">Automatik</v-card-title>
        <v-card-subtitle>Einstellungen der Module, die automatisch Einträge erstellen</v-card-subtitle>
        <v-card-text>
          <v-expansion-panels color="black">
            <v-expansion-panel>
              <v-expansion-panel-title>Abfallkalender</v-expansion-panel-title>
              <v-expansion-panel-text>
                <v-card>
                  <v-card-title class="mb-n4">Abfallkalender</v-card-title>
                  <v-card-subtitle>Einstellungen</v-card-subtitle>
                  <v-card-text>
                    <ul class="admin-info">
                      <li><pre>Link:                </pre>{{ moduleTrash.calendar_link }}</li>
                      <li><pre>Abruf (Zuletzt):     </pre>{{ moduleTrash.last_fetched ?? 'Noch nie!' }}</li>
                      <li><pre>Abruf (Erfolgreich): </pre>{{ moduleTrash.last_updated ?? 'Noch nie!' }}</li>
                      <li><pre>Aktuell bis:         </pre>{{ moduleTrash.uptodate ?? 'Noch nie!' }}</li>
                    </ul>
                  </v-card-text>
                  <v-divider></v-divider>
                  <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="changeModuleTrash" text="Ändern"></v-btn>
                  </v-card-actions>
                </v-card>
              </v-expansion-panel-text>
            </v-expansion-panel>
            <v-expansion-panel>
              <v-expansion-panel-title>Sharepoint</v-expansion-panel-title>
              <v-expansion-panel-text>
                <v-card>
                  <v-card-title class="mb-n4">Sharepoint</v-card-title>
                  <v-card-subtitle>Einstellungen</v-card-subtitle>
                  <v-card-text>
                    <ul class="admin-info">
                      <li><pre>Link:                </pre>{{ !moduleSharepoint.sharepoint_link ? '--' : moduleSharepoint.sharepoint_link }}</li>
                      <li><pre>Nutzer:              </pre>{{ !moduleSharepoint.username ? '--' : moduleSharepoint.username }}</li>
                      <li><pre>Abruf (Zuletzt):     </pre>{{ moduleSharepoint.last_fetched ?? 'Noch nie!' }}</li>
                      <li><pre>Abruf (Erfolgreich): </pre>{{ moduleSharepoint.last_updated ?? 'Noch nie!' }}</li>
                      <li><pre>Aktuell bis:         </pre>{{ moduleSharepoint.uptodate ?? 'Noch nie!' }}</li>
                    </ul>
                  </v-card-text>
                  <v-divider></v-divider>
                  <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="changeModuleSharepoint" text="Ändern"></v-btn>
                  </v-card-actions>
                </v-card>
              </v-expansion-panel-text>
            </v-expansion-panel>
            <v-expansion-panel>
              <v-expansion-panel-title>NINA</v-expansion-panel-title>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card-text>
      </v-card>

    </content>

    <StationSettingsDialog ref="stationSettingsDialog" />
    <ModuleTrashDialog ref="moduleTrashDialog" />
    <ModuleSharepointDialog ref="moduleSharepointDialog" />

    <EditInfoDialog ref="editInfoDialog" />
    <EditEventDialog ref="editEventDialog" />
    <EditTaskDialog ref="editTaskDialog" />
    <EditRecurringDialog ref="editRecurringDialog" />
    <DeleteDialog ref="deleteDialog" />

  </main>
</template>
<style lang="scss" scoped>

#admin
{

  max-width: 1024px;
  margin: 0 auto;

  content
  {
    display: flex;
    flex-direction: column;
    padding: 1rem;
    gap: 1rem;
  }

  .admin-info
  {
    & pre
    {
      font-family: 'Roboto Mono';
      font-weight: 600;
      display: inline;
      font-size: 0.8rem;
      margin-right: .75rem;
      letter-spacing: 0;
    }
    & li
    {
      overflow: hidden;
      text-wrap: nowrap;
      text-overflow: ellipsis;
      position: relative;
    }
  }

}

</style>
<style lang="css">

.v-expansion-panel-text__wrapper
{
  padding: 0; /* remove padding inside expansion content */
}

.action-column {
  white-space: nowrap; /* Prevent content from wrapping */
  width: 1px;          /* This tricks the browser into sizing the column based on its content */
}

</style>