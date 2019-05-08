<?php

GWT('Test sign() #1: no any params', [], function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        $sdk->sign();
    } catch (\Exception $e) {
        return true;
    }
}, function ($result) {
    return $result === true;
});

GWT('Test stringify() #1: empty array', [], function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        return $sdk->stringify($given);
    } catch (\Exception $e) {
        return false;
    }
}, function ($result) {
    return $result === '';
});

GWT('Test stringify() #2: lower case', ['A' => 1], function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        return $sdk->stringify($given);
    } catch (\Exception $e) {
        return false;
    }
}, function ($result) {
    return $result === http_build_query(['a' => 1]);
});

GWT('Test stringify() #3: key sort', ['z' => 0, 'A' => 1], function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        return $sdk->stringify($given);
    } catch (\Exception $e) {
        return false;
    }
}, function ($result) {
    return $result === http_build_query(['a' => 1, 'z' => 0]);
});

GWT('Test stringify() #4: key sort', ['z' => 0, 'A' => 1], function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        return $sdk->stringify($given);
    } catch (\Exception $e) {
        return false;
    }
}, function ($result) {
    return $result === http_build_query(['a' => 1, 'z' => 0]);
});

GWT('Test setVerb() #1: upper case', 'get', function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        $sdk->setVerb($given);
        return $sdk->getVerb();
    } catch (\Exception $e) {
        return false;
    }
}, function ($result) {
    return $result === 'GET';
});

GWT('Test demo #1: success', null, function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        $sdk->setSecret(microtime())
            ->setRealm('sdk-demo')
            ->setClient('sdk-demo-appid')
            ->setTimestamp(time())
            ->setNonce(mt_rand(10000, 99999))
            ->setHost('api.app1.demo')
            ->setVerb('get')
            ->setPath('/resource')
            ->setParameters(['a' => 1, 'b' => 2]);

        return $sdk->sign();
    } catch (\Exception $e) {
        return false;
    }
}, function ($result) {
    return is_string($result) && (strlen($result) === 64);
});

GWT('Test demo #2: success with fixed argvs', null, function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        $sdk->setSecret('sdk-demo-secret')
            ->setRealm('sdk-demo')
            ->setClient('sdk-demo-appid')
            ->setTimestamp(1557219029)
            ->setNonce(12121)
            ->setHost('api.app1.demo')
            ->setVerb('get')
            ->setPath('/resource')
            ->setParameters(['a' => 1, 'b' => 2]);

        return $sdk->sign();
    } catch (\Exception $e) {
        return false;
    }
}, function ($result) {
    return $result === '2e655f2a173dde74949579517f538af770d2958f2d6325ddd028e45104c5f91a';
});

GWT('Test token() #1: no any params', [], function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        return $sdk->token();
    } catch (\Exception $e) {
        return true;
    }
}, function ($result) {
    return $result === true;
});

GWT('Test token() #2: success with fixed argvs', null, function ($given) {
    try {
        $sdk = new \Dof\Framework\OFB\Sdk\V1\HttpHmacAuthClientSdk;
        $sdk->setSecret('sdk-demo-secret')
            ->setRealm('sdk-demo')
            ->setClient('sdk-demo-appid')
            ->setTimestamp(1557219029)
            ->setNonce(12121)
            ->setHost('api.app1.demo')
            ->setVerb('get')
            ->setPath('/resource')
            ->setParameters(['a' => 1, 'b' => 2]);

        return $sdk->token();
    } catch (\Exception $e) {
        return false;
    }
}, function ($result) {
    return $result === 'MS4wCmRvZi1waHAtaHR0cC1obWFjCnNoYTI1NgpzZGstZGVtbwpzZGstZGVtby1hcHBpZAoxNTU3MjE5MDI5CjEyMTIxCgoKMmU2NTVmMmExNzNkZGU3NDk0OTU3OTUxN2Y1MzhhZjc3MGQyOTU4ZjJkNjMyNWRkZDAyOGU0NTEwNGM1ZjkxYQ==';
});
