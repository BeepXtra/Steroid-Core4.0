/**
 * FOR DEMO PURPOSES ONLY
 **/

"use strict";

// document ready
$(function() {
  var $accordionColorSelector = $("#accordion-theme-current");
  var $accordion = $("#accordion-colored");

  $accordion.addClass("accordion-" + $accordion.data("current"));
  $accordionColorSelector.addClass("btn-outline-" + $accordion.data("current"));

  $("#demo-accordion-theme-selector").on("click", ".dropdown-item", function(
    e
  ) {
    e.preventDefault();

    var color = $(this).data("color");
    var current = $accordion.data("current");

    $accordion
      .removeClass("accordion-" + current)
      .addClass("accordion-" + color);

    $accordion.data("current", color);

    // Update the selector text
    $accordionColorSelector.html(color);
    $accordionColorSelector
      .removeClass("btn-outline-" + current)
      .addClass("btn-outline-" + color);
  });

  $(".navigation", ".demo-blocks").each(function(i, e) {
    var $element = $(e);

    $(".navbar-toggler", e).on("click", function() {
      $element.toggleClass("navbar-expanded");
    });
  });

  /**
   * ANIMATION BAR
   **/
  (function() {
    $(".horizontal-demo-bars").animateBar({
      orientation: "horizontal",
      step: 100,
      duration: 1000,
      elements: [
        { label: "Implementation", value: 19 }, // style: { progress: "progress-4" }
        { label: "Design", value: 75 },
        { label: "Branding", value: 34 },
        { label: "Beauty", value: 55 },
        { label: "Responsiveness", value: 89 }
      ]
    });

    $(".vertical-demo-bars").animateBar({
      orientation: "vertical",
      step: 100,
      duration: 1000,
      elements: [
        { value: 49 },
        { value: 75 },
        { value: 34 },
        { value: 55 },
        { value: 89 }
      ]
    });
  })();
});
