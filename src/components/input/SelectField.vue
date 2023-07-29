<script setup lang="ts">
import { onMounted, ref, watch, onBeforeUnmount } from "vue";
import useFormValidation from "./validations/useFormValidation";

// import { countries } from "../../components/inputs/Countries";

const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  placeholder: {
    type: String,
    required: true,
  },
  inputShort: {
    type: Boolean,
    default: false,
  },
  currentValue: {
    type: String,
    default: "",
  },
  model: {
    type: String,
    default: "",
  },
  inputType: {
    type: String,
    default: "text",
  },
  required: {
    type: Boolean,
    default: true,
  },
});

let input = ref(props.currentValue);
const { validateNotEmptyField, errors } = useFormValidation();

const countries = [];

const showError = ref(false);

function keyupShowError() {
  showError.value = true;
}

onMounted(() => {
  if (isRequired()) {
    validateInput();
  }
});

function isRequired() {
  if (props.required) {
    return true;
  }
  return false;
}

watch(props, () => {
  input = ref(props.currentValue);
  validateInput();
});

const validateInput = () => {
  if (isRequired()) {
    validateNotEmptyField(props.label, props.model, input.value);
  }
};

onBeforeUnmount(() => {
  delete errors[props.model];
});
</script>

<template>
  <div class="" :id="model">
    <label class="font-semibold" :for="label + model" v-text="label">
    </label>
    <div class="text-red-500">
      <span v-if="showError && isRequired()">
        {{ errors[model] }}</span>
    </div>

    <select class="form-select w-full" :class="{
      // 'govuk-input--error': errors[key],
      'govuk-input--width-10': inputShort,
    }" :id="label" v-model.trim="input" :placeholder="placeholder" @keyup="keyupShowError()"
      @input="$emit('update:modelValue', $event.target.value)" :required="isRequired()">
      <option value="">Vybrať</option>
      <option v-for="item in items" :key="item.value" :value="item.value"
        :selected="currentValue === item.value">
        {{ item.name }}
      </option>
    </select>
  </div>
</template>