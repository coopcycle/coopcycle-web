<?php


namespace AppBundle\Utils;
use Symfony\Component\Validator\ConstraintViolationList;


class ValidationUtils
{

    /**
     * Transform a ConstraintViolationList to a JSON-serializable array.
     *
     * @param ConstraintViolationList $errors
     * @return array
     */
    public static function serializeValidationErrors(ConstraintViolationList $errors)
    {
        $validationsArray = [];

        foreach ($errors->getIterator()->getArrayCopy() as $violation) {
            $validationsArray[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $validationsArray;
    }

}