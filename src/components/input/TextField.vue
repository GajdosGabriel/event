<script setup lang="ts">
import { onMounted, ref, watch, onBeforeUnmount } from "vue";
import useFormValidation from "./validations/useFormValidation.js";

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  placeholder: {
    type: String,
    required: true,
  },
  currentValue: {
    type: String,
    default: "",
  },
  model: {
    type: String,
    default: "",
  },
  required: {
    type: Boolean,
    default: true,
  },
});

let input = ref(props.currentValue);
const { validateNotEmptyField, errors } = useFormValidation();

onMounted(() => {
  if (props.required) {
    validateInput();
  }
});

watch(props, () => {
  input = ref(props.currentValue);
  validateInput();
});

const validateInput = () => {
  if (props.required) {
    validateNotEmptyField(props.label, props.model, input.value);
  }
};

onBeforeUnmount(() => {
  delete errors[props.model];
});
</script>

<template>
  <div class="govuk-form-group" :id="model">
    <span class="govuk-error-message">
      {{ errors[model] }}
    </span>
    <textarea class="govuk-textarea" :class="{}" :id="label" v-model.trim="input" :placeholder="placeholder"
      @keyup="validateInput" @blue="validateInput" @input="$emit('update:modelValue', $event.target.value)"
      :required="required" rows="5"></textarea>
  </div>
</template>