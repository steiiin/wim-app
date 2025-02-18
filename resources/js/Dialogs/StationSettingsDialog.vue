<script setup>

/**
 * StationSettingsDialog - Dialog component
 *
 * This Dialog changes the station settings.
 *
 */

// #region Imports

// Vue composables
import { ref, computed, onMounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import ShortAlert from '@/Components/ShortAlert.vue'
import { CloneHelper } from '@/Utils/CloneHelper'

// #endregion

// #region Dialog

  const modalVisible = ref(false)
  const modalResolve = ref(null)

  const open = (props) => {
    settingsForm.station_name = props.station_name;
    settingsForm.station_location = CloneHelper.obj(props.station_location);
    modalVisible.value = true
    return new Promise((resolve) => {
      modalResolve.value = resolve;
    });
  }

  const save = () => {
    if (!isFormValid.value) { return }

    settingsForm.post('/set-settings', {
      preserveScroll: true,
      onSuccess: () => {
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

// #region StationSettings

  const settingsForm = useForm({
    station_name: '',
    station_location: {
      lat: '',
      long: ''
    },
  });
  const isFormValid = ref(false)

  // conversion
  const stationLocationString = computed({
    get() {
      const { lat, long } = settingsForm.station_location
      return lat && long ? `${lat},${long}` : ''
    },
    set(value) {
      const parts = value.split(',').map(part => part.trim())
      if (parts.length === 2) {
        settingsForm.station_location.lat = parts[0]
        settingsForm.station_location.long = parts[1]
      } else {
        // If the input is invalid, clear the fields or handle as needed
        settingsForm.station_location.lat = ''
        settingsForm.station_location.long = ''
      }
    }
  })

  // validation rules
  const stationNameRule = v => !!v || 'Du musst einen Namen eintragen.'
  const stationLocationRule = v => {
    if (!v) {
      return 'Du musst Koordinaten eingeben.'
    }
    const regex = /^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/
    if (!regex.test(v)) {
      return 'Gib eine gültige Ortsangabe (xx.xxx, xx.xxx) an.'
    }
    const [lat, long] = v.split(',').map(Number)
    if (lat < -90 || lat > 90) {
      return 'Latitude muss zwischen -90 und 90 sein.'
    }
    if (long < -180 || long > 180) {
      return 'Longitude muss zwischen -180 und 180 sein.'
    }
    return true
  }

// #endregion

// #region Lifecycle

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="modalVisible" width="auto" :persistent="settingsForm.processing">
    <v-card style="min-width: 400px;">
      <v-card-title class="mb-n4">Wache</v-card-title>
      <v-card-subtitle>Allgemeine Einstellungen</v-card-subtitle>
      <v-card-text>
        <v-form :disabled="settingsForm.processing" v-model="isFormValid" @submit.prevent="save" validate-on="input">
          <v-text-field v-model="settingsForm.station_name" :rules="[stationNameRule]" :counter="30"
            label="Name"></v-text-field>
          <v-text-field class="my-2" v-model="stationLocationString" :rules="[stationLocationRule]"
            label="Standort"></v-text-field>
          <hint>
            Rufe die Koordinaten unter <a href="https://www.gpskoordinaten.de/"
              target="_blank">https://gpskoordinaten.de</a> ab <br>
            und füge den Text unter 'Lat,Long' hier ein.
          </hint>
        </v-form>
        <ShortAlert ref="dialogAlert" />
      </v-card-text>
      <template v-slot:actions>
        <v-btn @click="cancel" :disabled="settingsForm.processing">Abbrechen</v-btn>
        <v-btn @click="save" :disabled="!isFormValid" :loading="settingsForm.processing"
          color="success">Speichern</v-btn>
      </template>
    </v-card>
  </v-dialog>
</template>
<style lang="scss" scoped></style>