<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const visible = ref(false)
const form = useForm({ calendars: [] })
const icons = [
  { title: 'Information', value: 'mdi-information' },
  { title: 'Fahrzeug', value: 'mdi-ambulance' },
  { title: 'MPG', value: 'mdi-medical-bag' },
  { title: 'Hygiene', value: 'mdi-hand-wash' },
]
const required = value => !!value?.trim() || 'Bitte ausfüllen.'
const formRef = ref(null)
const activePanel = ref(null)
const panelKeys = ref([])
let nextPanelKey = 0

const open = ({ calendars }) => {
  form.clearErrors()
  form.calendars = calendars.map(({ id, name, url, icon }) => ({ id, name, url, icon }))
  panelKeys.value = calendars.map(() => nextPanelKey++)
  activePanel.value = null
  visible.value = true
}
const add = () => {
  form.clearErrors()
  form.calendars.push({ name: '', url: '', icon: 'mdi-information' })
  const key = nextPanelKey++
  panelKeys.value.push(key)
  activePanel.value = key
}
const remove = index => {
  form.clearErrors()
  if (activePanel.value === panelKeys.value[index]) activePanel.value = null
  form.calendars.splice(index, 1)
  panelKeys.value.splice(index, 1)
}
const expandFirstInvalidCalendar = hasError => {
  const index = form.calendars.findIndex((calendar, index) => hasError(index))
  if (index !== -1) activePanel.value = panelKeys.value[index]
}
const save = async () => {
  if (form.processing) return
  const { valid, errors } = await formRef.value.validate()
  if (!valid) {
    expandFirstInvalidCalendar(index => errors.some(({ id }) => String(id).startsWith(`calendar.${panelKeys.value[index]}.`)))
    return
  }
  form.post('/set-module-ical', {
    preserveScroll: true,
    onSuccess: () => { visible.value = false },
    onError: errors => {
      expandFirstInvalidCalendar(index => Object.keys(errors).some(field => field.startsWith(`calendars.${index}.`)))
    },
  })
}
defineExpose({ open })
</script>

<template>
  <v-dialog v-model="visible" max-width="720" :persistent="form.processing" scrollable>
    <v-card title="Wachenkalender" subtitle="iCalendar-Abonnements">
      <v-card-text>
        <v-form ref="formRef" :disabled="form.processing" @submit.prevent="save">

          <v-alert v-if="form.errors.calendars" type="error" class="mb-4">{{ form.errors.calendars }}</v-alert>
          <p v-if="!form.calendars.length" class="mb-4">Noch keine Kalender eingerichtet.</p>
          <v-expansion-panels v-model="activePanel" variant="accordion" class="mb-4">
            <v-expansion-panel v-for="(calendar, index) in form.calendars" :key="panelKeys[index]" :value="panelKeys[index]">
              <v-expansion-panel-title color="black">
                <v-icon :icon="calendar.icon" class="mr-2" />
                {{ calendar.name?.trim() || 'Neuer Kalender' }}
              </v-expansion-panel-title>
              <v-expansion-panel-text eager style="margin:1rem">
                <v-text-field v-model="calendar.name" :name="`calendar.${panelKeys[index]}.name`" label="Kalendername" :rules="[required]" maxlength="100"
                  :error-messages="form.errors[`calendars.${index}.name`]" />
                <v-text-field v-model="calendar.url" :name="`calendar.${panelKeys[index]}.url`" label="Kalenderlink (iCalendar)" :rules="[required]" maxlength="2048"
                  :error-messages="form.errors[`calendars.${index}.url`]" />
                <v-select v-model="calendar.icon" :name="`calendar.${panelKeys[index]}.icon`" label="Symbol" :items="icons" :prepend-inner-icon="calendar.icon"
                  :error-messages="form.errors[`calendars.${index}.icon`]">
                  <template #item="{ props, item }">
                    <v-list-item v-bind="props" :prepend-icon="item.value" />
                  </template>
                </v-select>
                <v-alert v-if="form.errors[`calendars.${index}.id`]" type="error">{{ form.errors[`calendars.${index}.id`] }}</v-alert>
                <v-btn variant="tonal" color="error" prepend-icon="mdi-delete" :disabled="form.processing" @click="remove(index)">Entfernen</v-btn>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
          <v-btn prepend-icon="mdi-plus" :disabled="form.processing" @click="add">Kalender hinzufügen</v-btn>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn :disabled="form.processing" @click="visible = false">Abbrechen</v-btn>
        <v-btn color="success" :loading="form.processing" :disabled="form.processing" @click="save">Speichern</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
