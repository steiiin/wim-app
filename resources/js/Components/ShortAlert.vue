<template>
  <transition name="fade">
    <v-alert v-if="isVisible"
      :type="alertType" dismissible
      @input="hide"
      class="short-alert">
      {{ message }}
      <span v-html="errorbag" />
    </v-alert>
  </transition>
</template>

<script setup>

import { ref } from 'vue'

const isVisible = ref(false)
const message = ref('')
const alertType = ref('')
const errorbag = ref('')
let hideTimer = null

const showMessage = (msg, alTyp='error', duration=3000) => {

  errorbag.value = ''

  message.value = msg
  alertType.value = alTyp
  isVisible.value = true

  if (hideTimer) {
    clearTimeout(hideTimer)
  }

  hideTimer = setTimeout(() => {
    hide()
  }, duration)

}

const showErrors = (errors, alTyp='error') => {

  showMessage('', alTyp)

  const listItems = Object.entries(errors)
    .map(([field, message]) => `<li><b>${field}:</b> ${message}</li>`)
    .join('');
  errorbag.value = `<ul>${listItems}</ul>`;

}

const hide = () => {
  isVisible.value = false
  if (hideTimer) {
    clearTimeout(hideTimer)
    hideTimer = null
  }
}

defineExpose({
  showMessage, showErrors,
})

</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.short-alert {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 1000; /* Ensure it appears above other elements */
}
</style>
