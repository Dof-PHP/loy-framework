<?php

GWT('TypeHint test #1: is array', 0, function ($given) {
    return \Dof\Framework\TypeHint::isArray($given);
}, function ($result) {
    return $result === false;
});

GWT('TypeHint test #2: is array', null, function ($given) {
    return \Dof\Framework\TypeHint::isArray($given);
}, function ($result) {
    return $result === false;
});

GWT('TypeHint test #3: is array', [], function ($given) {
    return \Dof\Framework\TypeHint::isArray($given);
}, function ($result) {
    return $result === true;
});

GWT('TypeHint test #4: is array', new \Dof\Framework\Collection(['foo' => 'bar']), function ($given) {
    return \Dof\Framework\TypeHint::isArray($given);
}, function ($result) {
    return $result === true;
});

GWT('TypeHint test #5: convert to array', new \Dof\Framework\Collection(['foo' => 'bar']), function ($given) {
    return \Dof\Framework\TypeHint::convertToArray($given);
}, function ($result) {
    return $result === ['foo' => 'bar'];
});

GWT('TypeHint test #6: convert to array', 0, function ($given) {
    return \Dof\Framework\TypeHint::convertToArray($given);
}, function ($result) {
    return $result === [0];
});

GWT('TypeHint test #7: convert to array', null, function ($given) {
    return \Dof\Framework\TypeHint::convertToArray($given);
}, function ($result) {
    return $result === [];
});
