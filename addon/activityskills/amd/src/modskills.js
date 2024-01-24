define(['jquery', 'core/modal_factory', 'core/str', 'core_form/modalform'], function($, ModalFactory, Str, ModalForm) {

    const SELECTORS = {
        table: '#mod_skills_list',
        editskill: '[data-target="toolskill-edit"]',
        skillsRow: '#mod_skills_list .skill-actions a.action-edit'
    };

    /**
     * Show the activity skill edit settings in the modal.
     */
    class ToolSkillsActivity {

        /**
         * Get the data and selectors.
         *
         * @param {*} skillID Skill ID
         * @param {*} courseID Coursae ID
         * @param {*} modID Module ID
         */
        constructor(skillID, courseID, modID) {

            this.SELECTORS = SELECTORS;
            this.skillCourseID = '';
            this.skillID = skillID;
            this.courseID = courseID;
            this.modID = modID;

            this.SELECTORS.root = '#mod_skills_list [data-skillid="' + this.skillID + '"]';
            this.addActionListiners();
        }

        getRoot() {
            return document.querySelector(this.SELECTORS.root);
        }

        /**
         * Show the course module form on the modal.
         */
        showContentForm() {

            var formClass = 'skilladdon_activityskills\\form\\course_mod_form';

            const modalForm = new ModalForm({

                formClass: formClass,
                // Add as many arguments as you need, they will be passed to the form:
                args: {courseid: this.courseID, skill: this.skillID, modid: this.modID},
                // Modal configurations, here set modal title.
                modalConfig: {title: Str.get_string('moduleskills', 'skilladdon_activityskills')},
            });

            modalForm.show();

            // Listen to events if you want to execute something on form submit.
            modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, function() {
                window.location.reload();
            });
        }

        /**
         * Show the content form, when click the config.
         */
        addActionListiners() {

            var self = this;

            this.getRoot().addEventListener('click', function(e) {

                if (e.target.closest(SELECTORS.editskill)) {
                    e.preventDefault();
                    self.showContentForm();
                }
            });
        }

        /**
         * Add event listenrs.
         *
         * @param {Integer} courseID
         * @param {Integer} modID
         */
        static createInstances(courseID, modID) {

            let skills = document.querySelectorAll(SELECTORS.skillsRow);

            const skillsList = [];

            if (skills !== null) {

                var skill;
                skills.forEach((skl) => {
                    var skillID = skl.dataset.skillid;
                    if (skillID in skillsList) {
                        skill = skillsList[skillID];
                    } else {
                        skill = new ToolSkillsActivity(skillID, courseID, modID);
                        skillsList[skillID] = skill;
                    }
                });
            }
        }
    }

    return {

        init: function(courseID, modID) {
            ToolSkillsActivity.createInstances(courseID, modID);
        }
    };
});
