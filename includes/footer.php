            </div>
            </main>
            </div>
            </div>

            <div class="modal fade calc-modal" id="calculatorModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="calc-shell">
                            <div class="calc-top">
                                <h5><i class="fa-solid fa-calculator me-2"></i>Hesap Makinesi</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>

                            <div class="calc-display-wrap">
                                <div class="calc-history" id="calcHistory"></div>
                                <div class="calc-display" id="calcDisplay">0</div>
                            </div>

                            <div class="calc-grid">
                                <button type="button" class="calc-btn calc-btn-danger" data-action="clear">AC</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-action="delete">⌫</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-value="%">%</button>
                                <button type="button" class="calc-btn calc-btn-op" data-value="/">÷</button>

                                <button type="button" class="calc-btn calc-btn-dark" data-value="7">7</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-value="8">8</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-value="9">9</button>
                                <button type="button" class="calc-btn calc-btn-op" data-value="*">×</button>

                                <button type="button" class="calc-btn calc-btn-dark" data-value="4">4</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-value="5">5</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-value="6">6</button>
                                <button type="button" class="calc-btn calc-btn-op" data-value="-">−</button>

                                <button type="button" class="calc-btn calc-btn-dark" data-value="1">1</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-value="2">2</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-value="3">3</button>
                                <button type="button" class="calc-btn calc-btn-op" data-value="+">+</button>

                                <button type="button" class="calc-btn calc-btn-dark calc-btn-zero" data-value="0">0</button>
                                <button type="button" class="calc-btn calc-btn-dark" data-value=".">.</button>
                                <button type="button" class="calc-btn calc-btn-equal" data-action="calculate">=</button>
                            </div>

                            <div class="calc-mini-note">Hızlı işlem için klavyeyi de kullanabilirsin</div>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

            <?php if (!empty($pageScripts ?? '')): ?>
                <?php echo $pageScripts; ?>
            <?php endif; ?>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const display = document.getElementById('calcDisplay');
                    const history = document.getElementById('calcHistory');
                    const modal = document.getElementById('calculatorModal');

                    if (!display || !history || !modal) return;

                    let current = '0';
                    let expression = '';

                    function updateDisplay() {
                        display.textContent = current || '0';
                        history.textContent = expression;
                    }

                    function appendValue(value) {
                        if (current === '0' && value !== '.') {
                            current = value;
                        } else {
                            current += value;
                        }
                        updateDisplay();
                    }

                    function isOperator(char) {
                        return ['+', '-', '*', '/', '%'].includes(char);
                    }

                    function appendOperator(op) {
                        if (current !== '' && current !== 'Hata') {
                            expression += current;
                            current = '';
                        }

                        if (expression === '' && op !== '-') return;

                        const lastChar = expression.slice(-1);
                        if (isOperator(lastChar)) {
                            expression = expression.slice(0, -1) + op;
                        } else {
                            expression += op;
                        }

                        updateDisplay();
                    }

                    function clearAll() {
                        current = '0';
                        expression = '';
                        updateDisplay();
                    }

                    function deleteLast() {
                        if (current && current !== '0' && current !== 'Hata') {
                            current = current.slice(0, -1);
                            if (current === '') current = '0';
                        } else if (expression.length > 0) {
                            expression = expression.slice(0, -1);
                        }
                        updateDisplay();
                    }

                    function calculate() {
                        let finalExpression = expression + (current !== '' && current !== 'Hata' ? current : '');

                        if (!finalExpression) return;

                        try {
                            finalExpression = finalExpression.replace(/%/g, '/100');
                            const result = Function('"use strict"; return (' + finalExpression + ')')();

                            history.textContent = finalExpression + ' =';
                            current = String(result);
                            expression = '';
                            updateDisplay();
                        } catch (e) {
                            current = 'Hata';
                            expression = '';
                            updateDisplay();

                            setTimeout(() => {
                                current = '0';
                                updateDisplay();
                            }, 1200);
                        }
                    }

                    document.querySelectorAll('.calc-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const value = this.dataset.value;
                            const action = this.dataset.action;

                            if (action === 'clear') {
                                clearAll();
                                return;
                            }

                            if (action === 'delete') {
                                deleteLast();
                                return;
                            }

                            if (action === 'calculate') {
                                calculate();
                                return;
                            }

                            if (typeof value !== 'undefined') {
                                if (isOperator(value)) {
                                    appendOperator(value);
                                } else {
                                    if (current === 'Hata') current = '0';
                                    if (value === '.' && current.includes('.')) return;
                                    appendValue(value);
                                }
                            }
                        });
                    });

                    document.addEventListener('keydown', function(e) {
                        if (!modal.classList.contains('show')) return;

                        if (/[0-9]/.test(e.key)) {
                            if (current === 'Hata') current = '0';
                            appendValue(e.key);
                        } else if (e.key === '.') {
                            if (!current.includes('.')) appendValue('.');
                        } else if (['+', '-', '*', '/', '%'].includes(e.key)) {
                            appendOperator(e.key);
                        } else if (e.key === 'Enter' || e.key === '=') {
                            e.preventDefault();
                            calculate();
                        } else if (e.key === 'Backspace') {
                            e.preventDefault();
                            deleteLast();
                        } else if (e.key === 'Escape') {
                            clearAll();
                        }
                    });

                    modal.addEventListener('shown.bs.modal', function() {
                        updateDisplay();
                    });

                    modal.addEventListener('hidden.bs.modal', function() {
                        clearAll();
                    });

                    updateDisplay();
                });
            </script>

            </body>

            </html>