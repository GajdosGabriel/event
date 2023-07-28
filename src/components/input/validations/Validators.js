
export default function useValidators() {
  const isEmpty = (fieldName, fieldValue) => {
    return !fieldValue ? `${fieldName} sa vyžaduje` : "";
  };

  const maxLength = (fieldName, fieldValue, max) => {
    return fieldValue.length > max
      ? `${fieldName} nesmie byť dlhšie ako ${max} znakov`
      : "";
  };
  const minLength = (fieldName, fieldValue, min) => {
    return fieldValue.length < min
      ? `${fieldName} musí obsahovať najmenej ${min} znaky`
      : "";
  };

  const isEmail = (fieldName, fieldValue) => {
    let re =
      /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return !re.test(fieldValue)
      ? "The input is not a valid " + fieldName + " address"
      : "";
  };


  return { isEmpty, minLength, maxLength, isEmail };
}