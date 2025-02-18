<script setup>

/**
 * EditRecurringDialog - Dialog component
 *
 * This Dialog opens/create an Recurring.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, watch } from 'vue'
  import { router, useForm } from '@inertiajs/vue3'

  // Local components
  import ShortAlert from '@/Components/ShortAlert.vue'
  import PayloadEdit from '@/Components/PayloadEdit.vue'
  import PayloadView from '@/Components/PayloadView.vue'
  import DateTimeEdit from '@/Components/DateTimeEdit.vue'
  import ToolbarCheckbox from '@/Components/ToolbarCheckbox.vue'

  // Local composables
  import { DateHelper } from '@/Utils/DateHelper';
  import { CloneHelper } from '@/Utils/CloneHelper'

// #endregion

// #region Dialog

  const modalVisible = ref(false)
  const modalResolve = ref(null)

  const open = (props) => {

    if (props?.edit)
    {

      editForm.id = props.id
      editForm.payload = CloneHelper.payload(props.payload)
      editForm.from = CloneHelper.date(props.from)
      editForm.dueto = CloneHelper.date(props.dueto)
      editForm.recurrence_type = CloneHelper.string(props.recurrence_type)
      editForm.weekday = props.weekday
      editForm.nth_day = props.nth_day

    }
    else
    {

      editForm.id = null
      editForm.payload = { title: '' }
      editForm.from = null
      editForm.dueto = null
      editForm.recurrence_type = 'daily'
      editForm.weekday = null
      editForm.nth_day = null

    }

    visiblePanel.value = 'payload'

    modalVisible.value = true
    return new Promise((resolve) => {
      modalResolve.value = resolve;
    });
  }

  const save = () => {
    if (!isFormValid.value) { return }

    editForm.post('/set-recurring', {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ['recurring'] })
        modalResolve.value(true)
        modalVisible.value = false
      },
      onError: (errors) => {
        dialogAlert.value.showMessage(JSON.stringify(errors))
      },
    })

  }

  const cancel = () => {
    modalResolve.value(false)
    modalVisible.value = false
  }

// #endregion
// #region Alert

  const dialogAlert = ref(null)

// #endregion

// #region EditRecurring

  const editForm = useForm({
    id: null,
    payload: null,
    from: null,
    dueto: null,
    recurrence_type: 'daily',
    weekday: null,
    nth_day: null,
  })
  const isFormValid = computed(() => isPayloadValid.value && isRuleValid.value)

  const visiblePanel = ref('payload')

  const isPayloadValid = computed(() => !!editForm.payload?.title)
  const isRuleValid = computed(() => {

    if (!editForm.from || !(editForm.from instanceof Date && !isNaN(editForm.from))) { return false }
    if (!editForm.dueto || !(editForm.dueto instanceof Date && !isNaN(editForm.dueto))) { return false }

    if (editForm.recurrence_type === 'daily')
    {
      return editForm.nth_day === null && editForm.weekday === null
    }
    else if (editForm.recurrence_type === 'weekly')
    {
      return editForm.weekday !== null
    }
    else if (editForm.recurrence_type === 'monthly-day')
    {
      return editForm.nth_day !== null && editForm.weekday === null
    }
    else if (editForm.recurrence_type === 'monthly-weekday')
    {
      return editForm.nth_day !== null && editForm.weekday !== null
    }
    return false
  })

  // #region Rules

    const ruleSnippet = computed(() => {
      if (!isRuleValid.value) { return '' }
      if (editForm.recurrence_type === 'daily') { return 'Täglich' }
      else if (editForm.recurrence_type === 'weekly') { return `Jede Woche am ${selectedWeekday.value}` }
      else if (editForm.recurrence_type === 'monthly-day') { return `Jeden Monat den ${selectedDayOfMonth.value} Tag`}
      else if (editForm.recurrence_type === 'monthly-weekday') { return `Jeden ${selectedNthWeek.value} ${selectedWeekday.value} im Monat`}
    })

    // #region Type

      const typeToggleValue = computed({
        get() {
          if (editForm.recurrence_type.startsWith('monthly-'))
          {
            return 'monthly'
          }
          else
          {
            return editForm.recurrence_type
          }
        },
        set(newValue) {

          editForm.nth_day = null
          editForm.weekday = null

          if (newValue === 'monthly')
          {
            currentMonthMode.value = '-day'
            editForm.recurrence_type = `monthly${currentMonthMode.value}`
          }
          else
          {
            editForm.recurrence_type = newValue
          }
        }
      })

      const inDailyMode = computed(() => editForm.recurrence_type === 'daily')
      const inWeeklyMode = computed(() => editForm.recurrence_type === 'weekly')
      const inDayOfMonthMode = computed(() => editForm.recurrence_type === 'monthly-day')
      const inWeekdayOfMonthMode = computed(() => editForm.recurrence_type === 'monthly-weekday')

    // #endregion

    const weekdays = DateHelper.getWeekdaysValues()
    const daysOfMonth = DateHelper.getDaysOfMonthValues()
    const nth_weeks = DateHelper.getNthWeekValues()

    const selectedWeekday = computed(() => DateHelper.getWeekdaysValues(editForm.weekday))
    const selectedDayOfMonth = computed(() => DateHelper.getDaysOfMonthValues(editForm.nth_day))
    const selectedNthWeek = computed(() => DateHelper.getNthWeekValues(editForm.nth_day))

    // #region MonthlyMode

      const currentMonthMode = ref(null)
      const monthModes = [
        { title: '... einem bestimmten X.ten Tag', value: '-day' },
        { title: '... einem X.ten Wochentag', value: '-weekday' },
      ]

      watch(
        () => currentMonthMode.value,
        (newValue) => {
          editForm.nth_day = null
          editForm.weekday = null
          editForm.recurrence_type = `monthly${newValue}`
        }
      )

    // #endregion

  // #endregion

// #endregion

// #region Lifecycle

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="modalVisible" width="100%" max-width="600px"  :persistent="editForm.processing">
    <v-card>
      <v-card-title class="mb-n4">Tagesaufgabe</v-card-title>
      <v-card-subtitle>{{ !editForm.id ? 'Erstellen' : 'Bearbeiten' }}</v-card-subtitle>
      <v-card-text>
        <v-form :disabled="editForm.processing" @submit.prevent="save" validate-on="input">

          <v-card class="mb-2">
            <PayloadView :payload="editForm.payload" :show-type-icon="false" style="padding: 1rem;" />
          </v-card>

          <v-expansion-panels v-model="visiblePanel"
            :eager="true" :mandatory="true" :static="true" variant="accordion">
            <v-expansion-panel title="Inhalt" color="black" :rounded="0" value="payload">
              <v-expansion-panel-text>
                <PayloadEdit v-model="editForm.payload" class="pa-2" />
              </v-expansion-panel-text>
            </v-expansion-panel>
            <v-expansion-panel color="black" :rounded="0" value="timing" :disabled="!isPayloadValid">
              <v-expansion-panel-title v-slot="{ expanded }">
                <v-row no-gutters>
                  <v-col class="d-flex justify-start" cols="2">
                    Regeln{{ expanded || !isRuleValid ? '' : ':' }}
                  </v-col>
                  <v-col style="opacity: .6" cols="10">
                    <v-row v-if="!expanded" no-gutters>
                      <v-col class="d-flex justify-start">
                        {{ ruleSnippet }}
                      </v-col>
                    </v-row>
                  </v-col>
                </v-row>
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <v-sheet class="pa-4">

                  <v-row no-gutters class="mb-3">
                    <v-col class="dtedit-sbs">
                      <DateTimeEdit v-model="editForm.from" mode="time"
                        title="Aktiv von:">
                      </DateTimeEdit>
                    </v-col>
                    <v-col class="dtedit-sbs">
                      <DateTimeEdit v-model="editForm.dueto" mode="time"
                        title="Bis:">
                      </DateTimeEdit>
                    </v-col>
                  </v-row>

                </v-sheet>
                <v-sheet class="mx-2 mb-2">

                  <v-toolbar density="compact">
                    <v-btn-toggle v-model="typeToggleValue" mandatory :disabled="editForm.processing"
                      color="primary" variant="text" class="btn-narrow-text">
                      <v-btn prepend-icon="mdi-calendar-today" value="daily" text="Täglich"></v-btn>
                      <v-btn prepend-icon="mdi-calendar-week" value="weekly" text="Wöchentlich"></v-btn>
                      <v-btn prepend-icon="mdi-calendar-month" value="monthly" text="Monatlich"></v-btn>
                    </v-btn-toggle>
                  </v-toolbar>

                </v-sheet>
                <v-sheet class="mx-2 mb-2" v-if="inWeeklyMode">

                  <v-select label="Jede Woche wiederholen am:" v-model="editForm.weekday"
                    :items="weekdays" hide-details >
                  </v-select>

                </v-sheet>
                <v-sheet class="mx-2 mb-2" v-if="typeToggleValue === 'monthly'">

                  <v-select label="Jeden Monat wiederholen an ..." v-model="currentMonthMode"
                    :items="monthModes" hide-details>
                  </v-select>

                </v-sheet>
                <v-sheet class="mx-2 mb-2" v-if="inDayOfMonthMode">

                  <v-select label="Welcher Tag?" v-model="editForm.nth_day"
                    :items="daysOfMonth" hide-details >
                  </v-select>

                </v-sheet>
                <v-sheet class="mx-2 mb-2" v-if="inWeekdayOfMonthMode">

                  <v-select label="Welcher Wochentag?" v-model="editForm.weekday"
                    :items="weekdays" hide-details >
                  </v-select>
                  <v-select label="Und den Wievielten?" v-model="editForm.nth_day"
                    :items="nth_weeks" hide-details v-if="editForm.weekday">
                    <template v-slot:item="{ props, item }">
                      <v-list-item v-bind="props" title="">Jeden <b>{{ item.title }}</b> {{ selectedWeekday }} im Monat</v-list-item>
                    </template>
                  </v-select>

                </v-sheet>

              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>

        </v-form>

        <ShortAlert ref="dialogAlert" />
      </v-card-text>
      <template v-slot:actions>
        <v-btn @click="cancel" :disabled="editForm.processing">Abbrechen</v-btn>
        <v-btn @click="save" :disabled="!isFormValid" :loading="editForm.processing"
          color="success">Speichern</v-btn>
      </template>
    </v-card>
  </v-dialog>
</template>
<style lang="scss" scoped>
.dtedit-sbs
{
  max-width: 140px;
}
</style>