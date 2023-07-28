import { reactive, computed } from "vue";
const errors = reactive({});

const errorsCounter = computed(() => Object.values(errors).filter(value => value != '').length );

import useValidators from "./Validators";

export default function useFormValidation() {
  const { isEmpty, maxLength, isEmail, municipality } = useValidators();

  const validateNotEmptyField = (fieldName, fieldModel, fieldValue) => {
    errors[fieldModel] = !fieldValue
      ? isEmpty(fieldName, fieldValue)
      : maxLength(fieldName, fieldValue, 240);
  };

  const validateEmailField = (fieldName, fieldModel, fieldValue) => {
    errors[fieldModel] = !fieldValue
      ? isEmpty(fieldName, fieldValue)
      : isEmail(fieldName, fieldValue);
  };

  const validateMunicipalityField = (fieldName, fieldModel, fieldValue) => {
    errors[fieldModel] = !fieldValue
      ? isEmpty(fieldName, fieldValue)
      : municipality(fieldName, fieldValue);
  };

  return { errors, errorsCounter, validateNotEmptyField, validateEmailField, validateMunicipalityField };
}