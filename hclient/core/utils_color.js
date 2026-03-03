/**
 * @file utils_color.js
 * @brief Generates CSS filters to approximate a target HEX color, starting from black.
 * @fileOverview This file defines a `Color` class for representing and manipulating RGB colors with CSS
 * filter-like operations (hueRotate, grayscale, sepia, saturate, brightness, contrast, invert).
 * It also includes a `Solver` class that uses a stochastic approximation algorithm (SPSA) to
 * find the optimal combination of CSS filter values to transform a base color (assumed black)
 * to a target HEX color. The primary utility function `hexToFilter(hex)` uses these classes
 * to produce a CSS filter string.
 * 
 * @project     Heurist academic knowledge management system
 *
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author MultiplyByZer0 (for the core algorithm via StackOverflow https://stackoverflow.com/a/43960991/604861)
 * @author Heurist Team
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

'use strict';

/**
 * Represents an RGB color and provides methods for color manipulations
 * that correspond to CSS filter functions (like hue-rotate, saturate, etc.).
 * This class is used to calculate the effects of CSS filters on a base color.
 */
class Color {
  /**
   * Creates a new Color instance.
   * @param {number} r - The red component (0-255).
   * @param {number} g - The green component (0-255).
   * @param {number} b - The blue component (0-255).
   */
  constructor(r, g, b) {
    this.set(r, g, b);
  }
  
  /**
   * Returns a string representation of the color in `rgb(r, g, b)` format.
   * Values are rounded to the nearest integer.
   * @returns {string} The CSS rgb string.
   */
  toString() {
    return `rgb(${Math.round(this.r)}, ${Math.round(this.g)}, ${Math.round(this.b)})`;
  }

  /**
   * Sets the RGB values of the color. Values are clamped to the 0-255 range.
   * @param {number} r - The red component.
   * @param {number} g - The green component.
   * @param {number} b - The blue component.
   */
  set(r, g, b) {
    this.r = this.clamp(r);
    this.g = this.clamp(g);
    this.b = this.clamp(b);
  }

  /**
   * Applies a hue rotation to the color.
   * The formula is based on the CSS filter `hue-rotate()`.
   * @param {number} [angle=0] - The angle of rotation in degrees.
   */
  hueRotate(angle = 0) {
    angle = angle / 180 * Math.PI;
    const sin = Math.sin(angle);
    const cos = Math.cos(angle);

    this.multiply([
      0.213 + cos * 0.787 - sin * 0.213,
      0.715 - cos * 0.715 - sin * 0.715,
      0.072 - cos * 0.072 + sin * 0.928,
      0.213 - cos * 0.213 + sin * 0.143,
      0.715 + cos * 0.285 + sin * 0.140,
      0.072 - cos * 0.072 - sin * 0.283,
      0.213 - cos * 0.213 - sin * 0.787,
      0.715 - cos * 0.715 + sin * 0.715,
      0.072 + cos * 0.928 + sin * 0.072,
    ]);
  }

  /**
   * Applies a grayscale filter to the color.
   * The formula is based on the CSS filter `grayscale()`.
   * @param {number} [value=1] - The amount of grayscale to apply (0 to 1).
   *                             0 means original color, 1 means completely grayscale.
   */
  grayscale(value = 1) {
    this.multiply([
      0.2126 + 0.7874 * (1 - value),
      0.7152 - 0.7152 * (1 - value),
      0.0722 - 0.0722 * (1 - value),
      0.2126 - 0.2126 * (1 - value),
      0.7152 + 0.2848 * (1 - value),
      0.0722 - 0.0722 * (1 - value),
      0.2126 - 0.2126 * (1 - value),
      0.7152 - 0.7152 * (1 - value),
      0.0722 + 0.9278 * (1 - value),
    ]);
  }

  /**
   * Applies a sepia filter to the color.
   * The formula is based on the CSS filter `sepia()`.
   * @param {number} [value=1] - The amount of sepia to apply (0 to 1).
   *                             0 means original color, 1 means full sepia.
   */
  sepia(value = 1) {
    this.multiply([
      0.393 + 0.607 * (1 - value),
      0.769 - 0.769 * (1 - value),
      0.189 - 0.189 * (1 - value),
      0.349 - 0.349 * (1 - value),
      0.686 + 0.314 * (1 - value),
      0.168 - 0.168 * (1 - value),
      0.272 - 0.272 * (1 - value),
      0.534 - 0.534 * (1 - value),
      0.131 + 0.869 * (1 - value),
    ]);
  }

  /**
   * Applies a saturation filter to the color.
   * The formula is based on the CSS filter `saturate()`.
   * @param {number} [value=1] - The saturation multiplier. 0 is unsaturated (grayscale),
   *                             1 is original saturation, values > 1 increase saturation.
   */
  saturate(value = 1) {
    this.multiply([
      0.213 + 0.787 * value,
      0.715 - 0.715 * value,
      0.072 - 0.072 * value,
      0.213 - 0.213 * value,
      0.715 + 0.285 * value,
      0.072 - 0.072 * value,
      0.213 - 0.213 * value,
      0.715 - 0.715 * value,
      0.072 + 0.928 * value,
    ]);
  }

  /**
   * Multiplies the current color components by a 3x3 color matrix.
   * This is a core operation for many CSS filter effects.
   * The resulting R, G, B values are clamped to the 0-255 range.
   * @param {Array<number>} matrix - A 9-element array representing the 3x3 matrix
   *                                 (e.g., [m0, m1, m2, m3, m4, m5, m6, m7, m8]).
   */
  multiply(matrix) {
    const newR = this.clamp(this.r * matrix[0] + this.g * matrix[1] + this.b * matrix[2]);
    const newG = this.clamp(this.r * matrix[3] + this.g * matrix[4] + this.b * matrix[5]);
    const newB = this.clamp(this.r * matrix[6] + this.g * matrix[7] + this.b * matrix[8]);
    this.r = newR;
    this.g = newG;
    this.b = newB;
  }

  /**
   * Adjusts the brightness of the color.
   * This is a linear transformation.
   * @param {number} [value=1] - The brightness multiplier. 0 creates black, 1 is original brightness.
   */
  brightness(value = 1) {
    this.linear(value);
  }
  /**
   * Adjusts the contrast of the color.
   * This is a linear transformation.
   * @param {number} [value=1] - The contrast multiplier. 0 creates a color half way to gray, 1 is original contrast.
   */
  contrast(value = 1) {
    this.linear(value, -(0.5 * value) + 0.5);
  }

  /**
   * Applies a linear transformation to each color component: `C = C * slope + intercept * 255`.
   * Values are clamped to the 0-255 range.
   * @param {number} [slope=1] - The slope for the linear transformation.
   * @param {number} [intercept=0] - The intercept for the linear transformation.
   */
  linear(slope = 1, intercept = 0) {
    this.r = this.clamp(this.r * slope + intercept * 255);
    this.g = this.clamp(this.g * slope + intercept * 255);
    this.b = this.clamp(this.b * slope + intercept * 255);
  }

  /**
   * Inverts the color.
   * The formula is based on the CSS filter `invert()`.
   * @param {number} [value=1] - The amount of inversion (0 to 1).
   *                             0 means original color, 1 means fully inverted.
   */
  invert(value = 1) {
    this.r = this.clamp((value + this.r / 255 * (1 - 2 * value)) * 255);
    this.g = this.clamp((value + this.g / 255 * (1 - 2 * value)) * 255);
    this.b = this.clamp((value + this.b / 255 * (1 - 2 * value)) * 255);
  }

  /**
   * Converts the current RGB color to HSL (Hue, Saturation, Lightness) values.
   * Hue is in degrees (0-360, but represented as 0-100 here for internal consistency with solver),
   * Saturation and Lightness are percentages (0-100).
   * Algorithm from https://stackoverflow.com/a/9493060/2688027 (licensed under CC BY-SA).
   * @returns {{h: number, s: number, l: number}} An object with h, s, l properties.
   */
  hsl() {
    // Code taken from https://stackoverflow.com/a/9493060/2688027, licensed under CC BY-SA.
    const r = this.r / 255;
    const g = this.g / 255;
    const b = this.b / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;

    if (max === min) {
      h = s = 0;
    } else {
      const d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      switch (max) {
        case r:
          h = (g - b) / d + (g < b ? 6 : 0);
          break;

        case g:
          h = (b - r) / d + 2;
          break;

        case b:
          h = (r - g) / d + 4;
          break;
      }
      h /= 6;
    }

    return {
      h: h * 100, // Representing hue as a percentage of 360 for internal calculations
      s: s * 100,
      l: l * 100,
    };
  }

  /**
   * Clamps a value to the 0-255 range.
   * @private
   * @param {number} value - The value to clamp.
   * @returns {number} The clamped value.
   */
  clamp(value) {
    if (value > 255) {
      value = 255;
    } else if (value < 0) {
      value = 0;
    }
    return value;
  }
}

/**
 * Finds the CSS filter values (invert, sepia, saturate, hue-rotate, brightness, contrast)
 * that can transform a base color (assumed to be black, (0,0,0)) to closely match a target color.
 * Uses a stochastic approximation algorithm (SPSA) to find the optimal filter values.
 * Based on the work by MultiplyByZer0: https://stackoverflow.com/a/43960991/604861
 */
class Solver {
  /**
   * Creates a new Solver instance.
   * @param {Color} target - The target Color object to match.
   * @param {Color} [baseColor] - The base color to transform. Although accepted, this parameter is not
   *                              currently used by the solver logic, which assumes a black base color (0,0,0)
   *                              due to the way `this.reusedColor` is initialized in `loss()`.
   */
  constructor(target, baseColor) {
    this.target = target;
    this.targetHSL = target.hsl();
    this.reusedColor = new Color(0, 0, 0); // Base color for filter application is black
  }

  /**
   * Solves for the CSS filter values.
   * It first performs a wide search for a good starting point, then a narrow search to refine.
   * @returns {{values: Array<number>, loss: number, filter: string}} An object containing:
   *           - `values`: An array of 6 filter values (percentages for invert, sepia, saturate, brightness, contrast; degrees for hue-rotate adjusted by 3.6).
   *           - `loss`: The calculated loss value indicating how close the result is to the target.
   *           - `filter`: The generated CSS filter string.
   */
  solve() {
    const result = this.solveNarrow(this.solveWide());
    return {
      values: result.values,
      loss: result.loss,
      filter: this.css(result.values),
    };
  }

  /**
   * Performs a wide-range search for filter values using SPSA.
   * This helps find a good general area in the solution space.
   * @private
   * @returns {{values: Array<number>, loss: number}} The best values and loss found.
   */
  solveWide() {
    const A = 5;
    const c = 15;
    const a = [60, 180, 18000, 600, 1.2, 1.2];

    let best = { loss: Infinity };
    for (let i = 0; best.loss > 25 && i < 3; i++) { // Iterate up to 3 times if loss is still high
      const initial = [50, 20, 3750, 50, 100, 100]; // Initial guess for filter values
      const result = this.spsa(A, a, c, initial, 1000);
      if (result.loss < best.loss) {
        best = result;
      }
    }
    return best;
  }

  /**
   * Performs a narrow-range search for filter values using SPSA, starting from the results of `solveWide`.
   * This refines the solution found by `solveWide`.
   * @private
   * @param {{values: Array<number>, loss: number}} wide - The result from `solveWide`.
   * @returns {{values: Array<number>, loss: number}} The refined best values and loss.
   */
  solveNarrow(wide) {
    const A = wide.loss;
    const c = 2;
    const A1 = A + 1;
    const a = [0.25 * A1, 0.25 * A1, A1, 0.25 * A1, 0.2 * A1, 0.2 * A1];
    return this.spsa(A, a, c, wide.values, 500);
  }

  /**
   * Implements the Simultaneous Perturbation Stochastic Approximation (SPSA) algorithm.
   * This is an optimization algorithm used to find the filter values that minimize the loss function.
   * @private
   * @param {number} A - SPSA tuning parameter.
   * @param {Array<number>} a - SPSA tuning parameter (array of 6 values).
   * @param {number} c - SPSA tuning parameter.
   * @param {Array<number>} values - Initial guess for the filter values (6 values: invert, sepia, saturate, hue-rotate, brightness, contrast).
   * @param {number} iters - Number of iterations to run the algorithm.
   * @returns {{values: Array<number>, loss: number}} The best filter values found and the corresponding loss.
   */
  spsa(A, a, c, values, iters) {
    const alpha = 1;
    const gamma = 0.16666666666666666;

    let best = null;
    let bestLoss = Infinity;
    const deltas = new Array(6);
    const highArgs = new Array(6);
    const lowArgs = new Array(6);

    for (let k = 0; k < iters; k++) {
      const ck = c / Math.pow(k + 1, gamma);
      for (let i = 0; i < 6; i++) {
        deltas[i] = Math.random() > 0.5 ? 1 : -1;
        highArgs[i] = values[i] + ck * deltas[i];
        lowArgs[i] = values[i] - ck * deltas[i];
      }

      const lossDiff = this.loss(highArgs) - this.loss(lowArgs);
      for (let i = 0; i < 6; i++) {
        const g = lossDiff / (2 * ck) * deltas[i];
        const ak = a[i] / Math.pow(A + k + 1, alpha);
        values[i] = fix(values[i] - ak * g, i);
      }

      const loss = this.loss(values);
      if (loss < bestLoss) {
        best = values.slice(0);
        bestLoss = loss;
      }
    }
    return { values: best, loss: bestLoss };

    function fix(value, idx) {
      let max = 100;
      if (idx === 2 /* saturate */) {
        max = 7500;
      } else if (idx === 4 /* brightness */ || idx === 5 /* contrast */) {
        max = 200;
      }

      if (idx === 3 /* hue-rotate */) {
        if (value > max) {
          value %= max;
        } else if (value < 0) {
          value = max + value % max;
        }
      } else if (value < 0) {
        value = 0;
      } else if (value > max) {
        value = max;
      }
      return value;
    }
  }

  /**
   * Calculates the "loss" or difference between the target color and the color produced by applying the given filters.
   * The base color for applying filters is black (0,0,0), as `this.reusedColor` is reset to black here.
   * The loss function sums the absolute differences in R, G, B, H, S, L components.
   * @private
   * @param {Array<number>} filters - Array of 6 filter values (percentages for invert, sepia, saturate, brightness, contrast; hue-rotate needs scaling).
   * @returns {number} The calculated loss value.
   */
  loss(filters) {
    // Argument is array of percentages.
    const color = this.reusedColor;
    color.set(0, 0, 0); // Reset to black, meaning filters are applied to a black base

    color.invert(filters[0] / 100);
    color.sepia(filters[1] / 100);
    color.saturate(filters[2] / 100);
    color.hueRotate(filters[3] * 3.6);
    color.brightness(filters[4] / 100);
    color.contrast(filters[5] / 100);

    const colorHSL = color.hsl();
    return (
      Math.abs(color.r - this.target.r) +
      Math.abs(color.g - this.target.g) +
      Math.abs(color.b - this.target.b) +
      Math.abs(colorHSL.h - this.targetHSL.h) +
      Math.abs(colorHSL.s - this.targetHSL.s) +
      Math.abs(colorHSL.l - this.targetHSL.l)
    );
  }

  /**
   * Formats an array of filter values into a CSS filter string.
   * @private
   * @param {Array<number>} filters - Array of 6 filter values.
   *        filters[0]: invert (%)
   *        filters[1]: sepia (%)
   *        filters[2]: saturate (%)
   *        filters[3]: hue-rotate (value is multiplied by 3.6 to get degrees)
   *        filters[4]: brightness (%)
   *        filters[5]: contrast (%)
   * @returns {string} The CSS filter string.
   */
  css(filters) {
    function fmt(idx, multiplier = 1) {
      return Math.round(filters[idx] * multiplier);
    }
    return `invert(${fmt(0)}%) sepia(${fmt(1)}%) saturate(${fmt(2)}%) hue-rotate(${fmt(3, 3.6)}deg) brightness(${fmt(4)}%) contrast(${fmt(5)}%);`;
  }
}

/**
 * Converts a HEX color string to a CSS filter string that approximates the color.
 * This is primarily used to colorize icons or elements that are originally black.
 * It assumes the base color being filtered is black.
 *
 * @param {string} hex - The HEX color string (e.g., "#FF0000" for red).
 * @param {bool} autoReattempt - Rerun the function on significant loss (5 or more)
 * @returns {string|undefined} A CSS filter string (e.g., "invert(20%) sepia(79%) ..."),
 *                             or undefined if the hex input is invalid.
 */
function hexToFilter(hex, autoReattempt = false) {
      
    const rgb = window.hWin.HEURIST4.ui.hexToRgb(hex); // Assumes HEURIST4.ui.hexToRgb is available
    if (rgb==null) {
      alert('Invalid format!');
      return;
    }

    const color = new Color(rgb.r, rgb.g, rgb.b);
    const solver = new Solver(color);
    const result = solver.solve();

    if(autoReattempt && result.loss >= 5){
      return hexToFilter(hex);
    }

    return result.filter;  
}
