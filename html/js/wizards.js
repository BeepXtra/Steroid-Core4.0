/*!
 Dashcore - HTML Startup Template, v1.0
 Forms JS file
 Copyright © 2021 5Studios.net
 http://5studios.net
 */

"use strict";
$(function($) {
  /** WIZARD
   * Each wizard has its own configuration, if you're going to use one or another please make sure the selector matches the one used bellow
   * You can remove the code you're not going to use to speed up the site.
   **/
  (function() {
    var defaultConfig = {
      //showStepURLhash: false, // not show the hash on URL
      theme: "dots",
      autoAdjustHeight: false,
      anchorSettings: {
        enableAllAnchors: true, // Activates all anchors clickable all times
        removeDoneStepOnNavigateBack: true // remove the "done" on visited steps when navigating back
      },
      enableURLhash: true // Enable selection of the step based on url hash
    };

    var doAjax = function($form, config) {
      var dfd = new $.Deferred();
      var settings = $.extend(true, {}, config, {
        url: $form.attr("action"),
        type: "POST",
        data: $form.serializeArray(),
        dataType: "json",
        beforeSend: function() {
          $formWizard.smartWizard("block");
        }
      });

      $.ajax(settings)
        .done(function(data) {
          if (data.result) {
            $form.trigger("form.submitted", [data]);

            $("input, textarea", $form).removeClass("error");
            $form.addClass("submitted");
          } else {
            if (data.errors) {
              $.each(data.errors, function(i, v) {
                var $input = $("[name$='[" + i + "]']", $form).addClass(
                  "error"
                );
                $input
                  .tooltip({ title: v, placement: "bottom", trigger: "manual" })
                  .tooltip("show")
                  .on("focus", function() {
                    $(this).tooltip("destroy");
                  });
              });
            }
          }

          $formWizard.smartWizard("unblock");
          dfd.resolve(data.result);
        })
        .fail(function() {
          $formWizard.smartWizard("unblock");

          //show failure message
          dfd.reject();
        });

      return dfd.promise();
    };

    // 1. BASIC WIZARD
    // This is a basic configuration, just setting the theme and default configuration
    $("#basic-wizard").smartWizard(defaultConfig);

    // 2. AJAX WIZARD
    // Ajax is not built-in, instead you can listen to stepContent event and provide the ajax contents.
    var $ajaxWizard = $("#ajax-wizard").smartWizard(defaultConfig);
    $ajaxWizard.on("stepContent", function(
      e,
      anchorObject,
      stepIndex,
      stepDirection
    ) {
      // Read data value from the anchor element
      var dataStep = anchorObject.data("step");
      var ajaxURL = "wizard/step-" + dataStep;

      // Return a promise object
      return new Promise(function(resolve, reject) {
        // Ajax call to fetch your content
        $.ajax({
          method: "GET",
          url: ajaxURL,
          beforeSend: function(xhr) {
            // Show the loader
            $ajaxWizard.smartWizard("loader", "show");
          }
        })
          .done(function(res) {
            // Resolve the Promise with the content
            resolve(res);

            // Hide the loader
            $ajaxWizard.smartWizard("loader", "hide");
          })
          .fail(function(err) {
            // Reject the Promise with error message to show as content
            reject("An error loading the resource");

            // Hide the loader
            $ajaxWizard.smartWizard("loader", "hide");
          });
      });
    });

    // 3. FORM WIZARD
    // Another way to load content through ajax is set the "contentUrl" via setting
    // this will send all requests to the same endpoint, you can take control of it via "step_number" variable sent automatically

    // Save the wizard variable, we'll used it below to work with it
    var $formWizard = $("#form-wizard");
    $formWizard
      .on("showStep", function(evt, anchorObject, stepNumber, stepDirection) {
        var dataStep = anchorObject.data("step");
        var validateOptions = {
          errorPlacement: function(error, element) {
            var $parent = element.parent();

            if ($parent.hasClass("input-group")) {
              error.insertAfter($parent);
            } else if ($parent.hasClass("has-icon")) {
              error.insertBefore($parent);
            } else if ($parent.hasClass("control")) {
              error.insertAfter(element.next(".control-label"));
            } else {
              error.insertAfter(element);
            }
          }
        };

        var $form = $("#form-step-" + dataStep);
        $form.validate(validateOptions, $formWizard);

        /** console.log("showStep", {
          formid: "#form-step-" + dataStep,
          form: $form
        }); */

        // some work with step-2 (pricing plans)
        if (stepNumber === 2) {
          $("input[type=radio]", $form).on("change", function(e) {
            $(e.target)
              .closest(".row")
              .find(".card")
              .removeClass("b b-3");

            $(e.target)
              .closest(".card")
              .addClass("b b-3");
          });
        }
      })
      .on("leaveStep", function(evt, anchorObject, stepNumber, stepDirection) {
        var dataStep = anchorObject.data("step");
        var $form = $("#form-step-" + dataStep, $formWizard);

        /** console.log("leaveStep", {
          formid: "#form-step-" + dataStep,
          form: $form,
          stepDirection
        }); **/

        // stepDirection === 'forward' :- this condition allows to do the form validation
        // only on forward navigation, that makes easy navigation on backwards still do the validation when going next
        if (stepDirection === 1 && $form.length) {
          if (!$form.valid()) {
            return false;
          }

          return doAjax($form);
        }

        return true;
      })
      .on("stepContent", function(e, anchorObject, stepIndex, stepDirection) {
        $formWizard.smartWizard("loader", "show");

        // Read data value from the anchor element
        var dataStep = anchorObject.data("step");
        var ajaxURL = "wizard/get-form?step_number=" + dataStep;

        return new Promise(function(resolve, reject) {
          // Ajax call to fetch your content
          $.ajax({
            method: "GET",
            url: ajaxURL,
            beforeSend: function(xhr) {
              $formWizard.smartWizard("loader", "show");
            }
          })
            .done(function(res) {
              // Resolve the Promise with the content
              resolve(res);

              // Hide the loader
              $formWizard.smartWizard("loader", "hide");
            })
            .fail(function(err) {
              // Reject the Promise with error message to show as content
              reject("Error loading the resource");

              // Hide the loader
              $formWizard.smartWizard("loader", "hide");
            });
        });
      })
      .smartWizard(
        $.extend(
          {},
          {
            selected: 0
            /*transition: {
                animation: 'slide-horizontal',
              }*/
          },
          defaultConfig
        )
      );
  })();
});
