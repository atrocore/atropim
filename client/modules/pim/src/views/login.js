

Espo.define('pim:views/login', 'class-replace!pim:views/login',
    Dep => Dep.extend({

        getLogoSrc: function () {
            const companyLogoId = this.getConfig().get('companyLogoId');
            if (!companyLogoId) {
                return this.getBasePath() + 'client/modules/pim/img/treo_pim_logo_white.svg';
            }
            return this.getBasePath() + '?entryPoint=LogoImage&id='+companyLogoId+'&t=' + companyLogoId;
        }

   })
);