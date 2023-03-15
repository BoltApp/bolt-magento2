define(['Bolt_Boltpay/js/utils/when-defined'], function (whenDefined) {
    'use strict';
    describe('Bolt_Boltpay/js/utils/when-defined', function () {
        var callback, callback2, object = {};

        beforeEach(function () {
            callback = jasmine.createSpy('callback');
            callback2 = jasmine.createSpy('callback2');
            object = {};
        });
        it('Check callback not to be called without assignment', function () {
            whenDefined(object, 'prop', callback);
            expect(callback).not.toHaveBeenCalled();
        });
        it('Check callback to be called on assignment', function () {
            whenDefined(object, 'prop', callback);
            object.prop = 'value';
            expect(callback).toHaveBeenCalled();
            expect(object.prop).toBe('value');
        });
        it('Check multiple callbacks on separate properties', function () {
            whenDefined(object, 'prop1', callback);
            whenDefined(object, 'prop2', callback);
            object.prop1 = 'value1';
            object.prop2 = 'value2';
            expect(callback).toHaveBeenCalledTimes(2);
            expect(object.prop1).toBe('value1');
            expect(object.prop2).toBe('value2');
        });
        it('Check multiple callbacks with unique id', function () {
            whenDefined(object, 'prop', callback, 'unique_id');
            whenDefined(object, 'prop', callback, 'unique_id');
            object.prop = 'value';
            expect(callback).toHaveBeenCalledTimes(1);
        });
        it('Check multiple callbacks without id and order of execution', function () {
            whenDefined(object, 'prop', callback);
            whenDefined(object, 'prop', function () {
                expect(callback).toHaveBeenCalledTimes(1);
            });
            object.prop = 'value';
            expect(object.prop).toBe('value');
        });
        it('Check value to be set before callback', function () {
            whenDefined(object, 'prop', function () {
                expect(object.prop).toBe('value');
            });
            object.prop = 'value';
            expect(object.prop).toBe('value');
        });
    });
});