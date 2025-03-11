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
  import { RuleHelper } from '@/Utils/RuleHelper'

  // Leaflet
  import 'leaflet/dist/leaflet.css'
  import * as L from 'leaflet'
import { nextTick } from 'vue'
import { onBeforeUnmount } from 'vue'

// #endregion

// #region Dialog

  const modalVisible = ref(false)
  const modalResolve = ref(null)

  const open = async (props) => {
    settingsForm.station_name = props.station_name;
    settingsForm.station_location = CloneHelper.obj(props.station_location);
    modalVisible.value = true

    await nextTick()
    initMap()

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
        killMap()
      },
      onError: (errors) => {
        dialogAlert.value.showMessage(JSON.stringify(errors))
      },
    })

  }

  const cancel = () => {
    modalResolve.value(false)
    modalVisible.value = false
    killMap()
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
  const stationLocationString = computed(() => `Lat: ${settingsForm.station_location.lat}, Long: ${settingsForm.station_location.long}`)

  // validation rules
  const stationNameRule = v => RuleHelper.empty(v)===true || 'Du musst einen Namen eintragen.'

// #endregion
// #region Map

  const stationMap = ref(null)
  const stationMarker = ref(null)

  const initMap = () => {

    stationMap.value = L.map('station_map')

    if (settingsForm.station_location.lat != 50.940408 && settingsForm.station_location.long != 6.991183)
    {
      stationMap.value.setView([settingsForm.station_location.lat, settingsForm.station_location.long], 14);
    }
    else
    {
      stationMap.value.fitBounds([
        [47.27011, 5.86633],  // Southwest Germany corner
        [55.05864, 15.04193]  // Northeast Germany corner
      ])
    }

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(stationMap.value)
    stationMap.value.on('click', selectNewLocation)

    putMarker([settingsForm.station_location.lat, settingsForm.station_location.long])

  }
  const killMap = () => { stationMap.value.remove() }

  const selectNewLocation = (e) => {
    putMarker(e.latlng)
    settingsForm.station_location.lat = e.latlng.lat.toFixed(6)
    settingsForm.station_location.long = e.latlng.lng.toFixed(6)
  }

  const putMarker = (latlng) => {
    if (stationMarker.value) { stationMarker.value.remove(); }
    stationMarker.value = L.marker(latlng)
    stationMarker.value.addTo(stationMap.value)
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
          <div id="station_map" style="height:50vh;"></div>
          <div>{{ stationLocationString }}</div>
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